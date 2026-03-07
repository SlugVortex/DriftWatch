// action/src/index.js
// DriftWatch GitHub Action — main entry point.
// Supports two modes:
//   "full"        — calls DriftWatch backend API with polling
//   "lightweight" — runs blast radius analysis entirely in-runner using Azure OpenAI

const core = require('@actions/core');
const github = require('@actions/github');
const https = require('https');
const http = require('http');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Make an HTTPS/HTTP request and return parsed JSON.
 */
function request(url, options = {}, body = null) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const lib = parsedUrl.protocol === 'https:' ? https : http;

        const reqOptions = {
            hostname: parsedUrl.hostname,
            port: parsedUrl.port || (parsedUrl.protocol === 'https:' ? 443 : 80),
            path: parsedUrl.pathname + parsedUrl.search,
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(options.headers || {}),
            },
        };

        const req = lib.request(reqOptions, (res) => {
            let data = '';
            res.on('data', (chunk) => (data += chunk));
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, data: JSON.parse(data) });
                } catch {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });

        req.on('error', reject);
        if (body) req.write(JSON.stringify(body));
        req.end();
    });
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function classifyRiskLevel(score) {
    if (score >= 81) return 'critical';
    if (score >= 61) return 'high';
    if (score >= 41) return 'medium';
    if (score >= 21) return 'low';
    return 'minimal';
}

function classifyDecision(score, threshold) {
    if (score >= threshold) return 'blocked';
    if (score >= threshold * 0.7) return 'pending_review';
    return 'approved';
}

// ---------------------------------------------------------------------------
// Scoring table (mirrors Stage 1A Archaeologist rubric)
// ---------------------------------------------------------------------------
const RISK_SCORES = {
    css_view_only: 2,
    new_standalone_function: 5,
    new_api_endpoint: 15,
    changed_function_signature: 20,
    auth_middleware_security: 30,
    sql_migration_query: 25,
    config_change: 20,
    shared_utility: 25,
};

function classifyFile(filename, patch) {
    const lower = filename.toLowerCase();
    const patchLower = (patch || '').toLowerCase();

    if (/\.(css|scss|less)$/.test(lower) || (/\.blade\.php$/.test(lower) && !patchLower.includes('function '))) {
        return { type: 'css_view_only', score: RISK_SCORES.css_view_only };
    }
    if (/middleware|auth|guard|security|token|session/i.test(lower)) {
        return { type: 'auth_middleware_security', score: RISK_SCORES.auth_middleware_security };
    }
    if (/migration|\.sql$/i.test(lower) || /Schema::|DB::|->table\(|CREATE TABLE|ALTER TABLE/i.test(patch || '')) {
        return { type: 'sql_migration_query', score: RISK_SCORES.sql_migration_query };
    }
    if (/config\/|\.env|\.json$|\.yaml$|\.yml$|\.toml$/i.test(lower)) {
        return { type: 'config_change', score: RISK_SCORES.config_change };
    }
    if (/Route::|@(Get|Post|Put|Delete|Patch)|app\.(get|post|put|delete)|router\./i.test(patch || '')) {
        return { type: 'new_api_endpoint', score: RISK_SCORES.new_api_endpoint };
    }
    if (/function\s+\w+\s*\(.*\).*\{/i.test(patch || '') && /(def |function |public |private |protected )/i.test(patch || '')) {
        return { type: 'changed_function_signature', score: RISK_SCORES.changed_function_signature };
    }
    if (/util|helper|shared|common|base|abstract/i.test(lower)) {
        return { type: 'shared_utility', score: RISK_SCORES.shared_utility };
    }
    return { type: 'new_standalone_function', score: RISK_SCORES.new_standalone_function };
}

// ---------------------------------------------------------------------------
// Full Mode — calls DriftWatch backend with polling
// ---------------------------------------------------------------------------

async function runFullMode() {
    const driftwatchUrl = core.getInput('driftwatch-url', { required: true });
    const apiToken = core.getInput('api-token', { required: true });
    const threshold = parseInt(core.getInput('risk-threshold')) || 70;
    const blockOnCritical = core.getInput('block-on-critical') !== 'false';

    const context = github.context;
    const prNumber = context.payload.pull_request?.number;
    const repo = context.payload.repository?.full_name;

    if (!prNumber || !repo) {
        core.setFailed('This action must be run on a pull_request event.');
        return;
    }

    core.info(`DriftWatch Full Mode: Analyzing PR #${prNumber} in ${repo}`);

    // POST to DriftWatch to trigger analysis
    const analyzeUrl = `${driftwatchUrl.replace(/\/$/, '')}/api/analyze`;
    const analyzeResponse = await request(analyzeUrl, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${apiToken}` },
    }, {
        pr_number: prNumber,
        repo_full_name: repo,
    });

    if (analyzeResponse.status !== 200 && analyzeResponse.status !== 201) {
        core.setFailed(`DriftWatch API returned HTTP ${analyzeResponse.status}: ${JSON.stringify(analyzeResponse.data)}`);
        return;
    }

    const jobId = analyzeResponse.data.job_id;
    core.info(`Analysis job started: ${jobId}`);

    // Poll for completion (max 5 minutes)
    const maxWait = 300000; // 5 minutes
    const pollInterval = 10000; // 10 seconds
    let elapsed = 0;
    let result = null;

    while (elapsed < maxWait) {
        await sleep(pollInterval);
        elapsed += pollInterval;

        const statusUrl = `${driftwatchUrl.replace(/\/$/, '')}/api/jobs/${jobId}/status`;
        const statusResponse = await request(statusUrl, {
            headers: { 'Authorization': `Bearer ${apiToken}` },
        });

        if (statusResponse.status !== 200) {
            core.warning(`Status poll returned HTTP ${statusResponse.status}`);
            continue;
        }

        const status = statusResponse.data.status;
        core.info(`Job status: ${status} (${Math.round(elapsed / 1000)}s elapsed)`);

        if (status === 'completed') {
            result = statusResponse.data;
            break;
        }
        if (status === 'failed' || status === 'error') {
            core.setFailed(`DriftWatch analysis failed: ${statusResponse.data.error || 'Unknown error'}`);
            return;
        }
    }

    if (!result) {
        core.setFailed('DriftWatch analysis timed out after 5 minutes.');
        return;
    }

    // Process result
    const riskScore = result.risk_score || 0;
    const riskLevel = result.risk_level || classifyRiskLevel(riskScore);
    const decision = result.decision || classifyDecision(riskScore, threshold);
    const summary = result.summary || `Risk score: ${riskScore}/100 (${riskLevel})`;

    core.setOutput('risk-score', riskScore.toString());
    core.setOutput('risk-level', riskLevel);
    core.setOutput('decision', decision);
    core.setOutput('summary', summary);

    core.info(`Risk Score: ${riskScore}/100 (${riskLevel})`);
    core.info(`Decision: ${decision}`);
    core.info(`Summary: ${summary}`);

    if (riskScore > threshold && blockOnCritical) {
        const services = (result.affected_services || []).join(', ');
        core.setFailed(
            `DriftWatch: Risk score ${riskScore}/100 exceeds threshold ${threshold}. ` +
            `Services at risk: ${services || 'unknown'}. Decision: ${decision}.`
        );
    }
}

// ---------------------------------------------------------------------------
// Lightweight Mode — runs entirely in-runner, no backend needed
// ---------------------------------------------------------------------------

async function runLightweightMode() {
    const threshold = parseInt(core.getInput('risk-threshold')) || 70;
    const blockOnCritical = core.getInput('block-on-critical') !== 'false';
    const azureEndpoint = core.getInput('azure-openai-endpoint', { required: true });
    const azureApiKey = core.getInput('azure-openai-api-key', { required: true });
    const azureDeployment = core.getInput('azure-openai-deployment') || 'gpt-4.1-mini';

    const context = github.context;
    const prNumber = context.payload.pull_request?.number;
    const repo = context.payload.repository?.full_name;
    const headSha = context.payload.pull_request?.head?.sha;

    if (!prNumber || !repo) {
        core.setFailed('This action must be run on a pull_request event.');
        return;
    }

    const token = process.env.GITHUB_TOKEN;
    if (!token) {
        core.setFailed('GITHUB_TOKEN is required for lightweight mode.');
        return;
    }

    core.info(`DriftWatch Lightweight Mode: Analyzing PR #${prNumber} in ${repo}`);

    // Step 1: Fetch changed files from GitHub API
    const filesUrl = `https://api.github.com/repos/${repo}/pulls/${prNumber}/files?per_page=100`;
    const filesResponse = await request(filesUrl, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'User-Agent': 'driftwatch-action',
        },
    });

    if (filesResponse.status !== 200) {
        core.setFailed(`GitHub API returned HTTP ${filesResponse.status} when fetching PR files.`);
        return;
    }

    const files = filesResponse.data;
    core.info(`Found ${files.length} changed files.`);

    // Step 2: Classify each file and read critical file contents
    const classifications = [];
    let totalScore = 0;
    const fileContents = [];

    for (const file of files.slice(0, 30)) {
        const classification = classifyFile(file.filename, file.patch || '');
        totalScore += classification.score;
        classifications.push({
            file: file.filename,
            change_type: classification.type,
            risk_score: classification.score,
            status: file.status,
            additions: file.additions,
            deletions: file.deletions,
        });

        // Read full content for high-risk files
        if (classification.score >= 15 && headSha) {
            try {
                const contentUrl = `https://api.github.com/repos/${repo}/contents/${encodeURIComponent(file.filename)}?ref=${headSha}`;
                const contentResponse = await request(contentUrl, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'User-Agent': 'driftwatch-action',
                    },
                });

                if (contentResponse.status === 200 && contentResponse.data.content) {
                    const decoded = Buffer.from(contentResponse.data.content, 'base64').toString('utf-8');
                    fileContents.push({
                        file: file.filename,
                        content: decoded.substring(0, 4000),
                    });
                }
            } catch {
                // Skip file content fetch errors
            }
        }
    }

    // Cap score at 100
    totalScore = Math.min(totalScore, 100);

    // Step 3: Call Azure OpenAI for AI-powered analysis
    const classificationsSummary = classifications
        .map((c) => `- ${c.file}: ${c.change_type} (${c.risk_score} pts, +${c.additions}/-${c.deletions})`)
        .join('\n');

    const fileContentBlock = fileContents
        .map((f) => `--- FILE: ${f.file} ---\n${f.content}\n`)
        .join('\n');

    const prompt = `You are DriftWatch, a deployment risk analysis AI. Analyze this PR and provide a risk assessment.

CHANGED FILES (${files.length} files, rule-based score: ${totalScore}/100):
${classificationsSummary}

${fileContents.length > 0 ? `HIGH-RISK FILE CONTENTS:\n${fileContentBlock}` : ''}

Based on the changes, provide a JSON response with:
- "risk_score": integer 0-100 (refine the rule-based score using code context)
- "risk_level": "minimal" | "low" | "medium" | "high" | "critical"
- "summary": 2-3 sentence plain English summary of the risk
- "affected_services": array of service names affected
- "top_risks": array of the top 3 risk factors (strings)

Respond with ONLY valid JSON, no markdown.`;

    let aiResult = null;
    try {
        const aiUrl = `${azureEndpoint.replace(/\/$/, '')}/openai/deployments/${azureDeployment}/chat/completions?api-version=2024-12-01-preview`;
        const aiResponse = await request(aiUrl, {
            method: 'POST',
            headers: { 'api-key': azureApiKey },
        }, {
            messages: [
                { role: 'system', content: 'You are DriftWatch, a deployment risk analyst. Respond with valid JSON only.' },
                { role: 'user', content: prompt },
            ],
            temperature: 0.3,
            max_tokens: 1000,
        });

        if (aiResponse.status === 200 && aiResponse.data.choices) {
            let content = aiResponse.data.choices[0].message.content;
            content = content.replace(/^```json\s*\n?/, '').replace(/\n?```\s*$/, '');
            aiResult = JSON.parse(content);
        }
    } catch (err) {
        core.warning(`Azure OpenAI call failed: ${err.message}. Using rule-based scoring only.`);
    }

    // Merge AI result with rule-based result
    const riskScore = aiResult?.risk_score || totalScore;
    const riskLevel = aiResult?.risk_level || classifyRiskLevel(riskScore);
    const summary = aiResult?.summary || `Rule-based analysis: ${totalScore}/100 across ${files.length} changed files.`;
    const affectedServices = aiResult?.affected_services || [];
    const decision = classifyDecision(riskScore, threshold);

    // Step 4: Create a GitHub Check with the results
    const octokit = github.getOctokit(token);
    const [owner, repoName] = repo.split('/');

    try {
        await octokit.rest.checks.create({
            owner,
            repo: repoName,
            name: 'DriftWatch Risk Analysis',
            head_sha: headSha,
            status: 'completed',
            conclusion: riskScore > threshold && blockOnCritical ? 'failure' : 'success',
            output: {
                title: `Risk Score: ${riskScore}/100 (${riskLevel})`,
                summary: [
                    `**Decision:** ${decision.toUpperCase()}`,
                    `**Risk Score:** ${riskScore}/100`,
                    `**Risk Level:** ${riskLevel}`,
                    `**Files Analyzed:** ${files.length}`,
                    affectedServices.length > 0 ? `**Affected Services:** ${affectedServices.join(', ')}` : '',
                    '',
                    summary,
                ].filter(Boolean).join('\n'),
                text: [
                    '## File Classifications',
                    '',
                    '| File | Type | Score |',
                    '|------|------|-------|',
                    ...classifications.map((c) => `| \`${c.file}\` | ${c.change_type} | ${c.risk_score} |`),
                ].join('\n'),
            },
        });
        core.info('GitHub Check created successfully.');
    } catch (err) {
        core.warning(`Could not create GitHub Check: ${err.message}`);
    }

    // Set outputs
    core.setOutput('risk-score', riskScore.toString());
    core.setOutput('risk-level', riskLevel);
    core.setOutput('decision', decision);
    core.setOutput('summary', summary);

    core.info(`Risk Score: ${riskScore}/100 (${riskLevel})`);
    core.info(`Decision: ${decision}`);

    if (riskScore > threshold && blockOnCritical) {
        core.setFailed(
            `DriftWatch: Risk score ${riskScore}/100 exceeds threshold ${threshold}. ` +
            `Services at risk: ${affectedServices.join(', ') || 'unknown'}. Decision: ${decision}.`
        );
    }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

async function run() {
    try {
        const mode = core.getInput('mode') || 'full';

        if (mode === 'lightweight') {
            await runLightweightMode();
        } else {
            await runFullMode();
        }
    } catch (error) {
        core.setFailed(`DriftWatch action failed: ${error.message}`);
    }
}

run();
