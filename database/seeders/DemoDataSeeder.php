<?php
// database/seeders/DemoDataSeeder.php
// Seeds historical incidents and demo PRs with full agent pipeline results.
// Creates the narrative: "payment-service keeps causing P1 incidents."

namespace Database\Seeders;

use App\Models\BlastRadiusResult;
use App\Models\DeploymentDecision;
use App\Models\DeploymentOutcome;
use App\Models\Incident;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('[DemoDataSeeder] Starting seed...');
        $this->seedIncidents();
        $this->seedDemoPullRequests();
        Log::info('[DemoDataSeeder] Seed complete.');
    }

    private function seedIncidents(): void
    {
        $incidents = [
            [
                'incident_id' => 'INC-001',
                'title' => 'Payment processing outage - rate calculator null pointer',
                'description' => 'Rate calculator returned null for international currencies causing payment failures. 23K transactions failed over 47 minutes.',
                'severity' => 1,
                'affected_services' => ['payment-service', 'billing-api', 'checkout-service'],
                'affected_files' => ['src/services/payment/calculator.py', 'src/api/routes/billing.py'],
                'root_cause_file' => 'src/services/payment/calculator.py',
                'duration_minutes' => 47,
                'engineers_paged' => 8,
                'occurred_at' => now()->subDays(12),
                'resolved_at' => now()->subDays(12)->addMinutes(47),
            ],
            [
                'incident_id' => 'INC-002',
                'title' => 'Billing API cascade failure after rate update deploy',
                'description' => 'Rate calculation changes triggered cascade failure in billing pipeline. Invoice generation backed up 2 hours.',
                'severity' => 1,
                'affected_services' => ['payment-service', 'billing-api', 'invoice-generator', 'notification-service'],
                'affected_files' => ['src/services/payment/calculator.py', 'src/utils/rate_helper.py'],
                'root_cause_file' => 'src/utils/rate_helper.py',
                'duration_minutes' => 124,
                'engineers_paged' => 12,
                'occurred_at' => now()->subDays(34),
                'resolved_at' => now()->subDays(34)->addMinutes(124),
            ],
            [
                'incident_id' => 'INC-003',
                'title' => 'Payment service memory leak after calculator refactor',
                'description' => 'Refactored rate calculator introduced memory leak causing OOM kills. Degraded over 6 hours before detection.',
                'severity' => 1,
                'affected_services' => ['payment-service', 'billing-api'],
                'affected_files' => ['src/services/payment/calculator.py', 'src/services/payment/cache.py'],
                'root_cause_file' => 'src/services/payment/calculator.py',
                'duration_minutes' => 380,
                'engineers_paged' => 6,
                'occurred_at' => now()->subDays(58),
                'resolved_at' => now()->subDays(58)->addMinutes(380),
            ],
            [
                'incident_id' => 'INC-004',
                'title' => 'User authentication service timeout spike',
                'description' => 'Auth service latency spiked to 30s after session store migration. Login failures for 15% of users.',
                'severity' => 2,
                'affected_services' => ['auth-service', 'user-service', 'api-gateway'],
                'affected_files' => ['src/services/auth/session_store.py', 'src/middleware/auth.py'],
                'root_cause_file' => 'src/services/auth/session_store.py',
                'duration_minutes' => 65,
                'engineers_paged' => 4,
                'occurred_at' => now()->subDays(7),
                'resolved_at' => now()->subDays(7)->addMinutes(65),
            ],
            [
                'incident_id' => 'INC-005',
                'title' => 'Notification service queue backup',
                'description' => 'Email notification queue filled up after template engine update. 50K notifications delayed.',
                'severity' => 3,
                'affected_services' => ['notification-service', 'email-service'],
                'affected_files' => ['src/services/notification/sender.py'],
                'root_cause_file' => 'src/templates/email_renderer.py',
                'duration_minutes' => 240,
                'engineers_paged' => 2,
                'occurred_at' => now()->subDays(15),
                'resolved_at' => now()->subDays(15)->addMinutes(240),
            ],
            [
                'incident_id' => 'INC-006',
                'title' => 'Search index corruption after schema migration',
                'description' => 'Elasticsearch mapping change caused partial data loss in search results.',
                'severity' => 2,
                'affected_services' => ['search-service', 'product-catalog', 'recommendation-engine'],
                'affected_files' => ['src/services/search/indexer.py'],
                'root_cause_file' => 'src/migrations/es_schema_v3.py',
                'duration_minutes' => 180,
                'engineers_paged' => 5,
                'occurred_at' => now()->subDays(22),
                'resolved_at' => now()->subDays(22)->addMinutes(180),
            ],
            [
                'incident_id' => 'INC-007',
                'title' => 'CDN cache invalidation failure',
                'description' => 'Static asset deployment failed to invalidate CDN caches. Stale JS bundles served 2 hours.',
                'severity' => 3,
                'affected_services' => ['cdn-service', 'frontend-app'],
                'affected_files' => ['deploy/cdn_invalidate.sh'],
                'root_cause_file' => 'deploy/cdn_invalidate.sh',
                'duration_minutes' => 120,
                'engineers_paged' => 2,
                'occurred_at' => now()->subDays(28),
                'resolved_at' => now()->subDays(28)->addMinutes(120),
            ],
            [
                'incident_id' => 'INC-008',
                'title' => 'Database connection pool exhaustion',
                'description' => 'New ORM query pattern exhausted connection pool within 30 minutes of deploy.',
                'severity' => 1,
                'affected_services' => ['user-service', 'order-service', 'payment-service', 'inventory-service'],
                'affected_files' => ['src/db/connection_pool.py'],
                'root_cause_file' => 'src/services/order/repository.py',
                'duration_minutes' => 35,
                'engineers_paged' => 7,
                'occurred_at' => now()->subDays(41),
                'resolved_at' => now()->subDays(41)->addMinutes(35),
            ],
            [
                'incident_id' => 'INC-009',
                'title' => 'API rate limiter misconfiguration',
                'description' => 'Rate limiter set to 10 req/min instead of 10K. External API consumers blocked.',
                'severity' => 2,
                'affected_services' => ['api-gateway', 'partner-api'],
                'affected_files' => ['src/middleware/rate_limiter.py'],
                'root_cause_file' => 'config/rate_limits.yaml',
                'duration_minutes' => 22,
                'engineers_paged' => 3,
                'occurred_at' => now()->subDays(45),
                'resolved_at' => now()->subDays(45)->addMinutes(22),
            ],
            [
                'incident_id' => 'INC-010',
                'title' => 'Inventory sync job race condition',
                'description' => 'Concurrent inventory updates created race condition. Negative stock for 127 products.',
                'severity' => 3,
                'affected_services' => ['inventory-service', 'order-service', 'product-catalog'],
                'affected_files' => ['src/services/inventory/sync_job.py'],
                'root_cause_file' => 'src/services/inventory/sync_job.py',
                'duration_minutes' => 95,
                'engineers_paged' => 3,
                'occurred_at' => now()->subDays(52),
                'resolved_at' => now()->subDays(52)->addMinutes(95),
            ],
            [
                'incident_id' => 'INC-011',
                'title' => 'Logging pipeline overflow crashed monitoring',
                'description' => 'Debug logging left enabled in production filled log aggregator.',
                'severity' => 3,
                'affected_services' => ['monitoring-service', 'log-aggregator'],
                'affected_files' => ['src/config/logging.py'],
                'root_cause_file' => 'src/config/logging.py',
                'duration_minutes' => 240,
                'engineers_paged' => 2,
                'occurred_at' => now()->subDays(60),
                'resolved_at' => now()->subDays(60)->addMinutes(240),
            ],
            [
                'incident_id' => 'INC-012',
                'title' => 'Checkout flow broken by payment gateway SSL cert rotation',
                'description' => 'Hardcoded SSL cert pinning broke when gateway rotated certificates.',
                'severity' => 1,
                'affected_services' => ['payment-service', 'checkout-service'],
                'affected_files' => ['src/services/payment/gateway_client.py'],
                'root_cause_file' => 'src/services/payment/gateway_client.py',
                'duration_minutes' => 55,
                'engineers_paged' => 5,
                'occurred_at' => now()->subDays(71),
                'resolved_at' => now()->subDays(71)->addMinutes(55),
            ],
            [
                'incident_id' => 'INC-013',
                'title' => 'Feature flag service crash during rollout',
                'description' => 'Uncaught exception for new flag type disabled all feature-gated functionality.',
                'severity' => 2,
                'affected_services' => ['feature-flag-service', 'frontend-app', 'api-gateway'],
                'affected_files' => ['src/services/feature_flags/evaluator.py'],
                'root_cause_file' => 'src/services/feature_flags/evaluator.py',
                'duration_minutes' => 40,
                'engineers_paged' => 4,
                'occurred_at' => now()->subDays(78),
                'resolved_at' => now()->subDays(78)->addMinutes(40),
            ],
            [
                'incident_id' => 'INC-014',
                'title' => 'Webhook delivery failure after endpoint migration',
                'description' => 'Webhook URLs not updated after service migration. Partner events missed 6 hours.',
                'severity' => 3,
                'affected_services' => ['webhook-service', 'partner-api', 'notification-service'],
                'affected_files' => ['src/services/webhooks/dispatcher.py'],
                'root_cause_file' => 'config/webhook_endpoints.yaml',
                'duration_minutes' => 360,
                'engineers_paged' => 2,
                'occurred_at' => now()->subDays(85),
                'resolved_at' => now()->subDays(85)->addMinutes(360),
            ],
        ];

        foreach ($incidents as $data) {
            Incident::updateOrCreate(['incident_id' => $data['incident_id']], $data);
        }
        Log::info('[DemoDataSeeder] Seeded ' . count($incidents) . ' historical incidents.');
    }

    private function seedDemoPullRequests(): void
    {
        // PR 1: High-risk payment service change (flagship demo)
        $pr1 = PullRequest::updateOrCreate(
            ['github_pr_id' => 'demo-pr-47'],
            [
                'repo_full_name' => 'acme-corp/platform',
                'pr_number' => 47,
                'pr_title' => 'Refactor payment rate calculator for multi-currency support',
                'pr_author' => 'alex-dev',
                'pr_url' => 'https://github.com/acme-corp/platform/pull/47',
                'base_branch' => 'main',
                'head_branch' => 'feature/multi-currency-rates',
                'files_changed' => 7,
                'additions' => 234,
                'deletions' => 89,
                'status' => 'scored',
            ]
        );

        BlastRadiusResult::updateOrCreate(['pull_request_id' => $pr1->id], [
            'affected_files' => [
                'src/services/payment/calculator.py', 'src/api/routes/billing.py',
                'src/utils/rate_helper.py', 'src/workers/invoice_generator.py',
                'src/services/notification/sender.py', 'tests/test_calculator.py', 'config/currencies.yaml',
            ],
            'affected_services' => ['payment-service', 'billing-api', 'invoice-generator', 'notification-service'],
            'affected_endpoints' => ['/api/v1/billing/calculate', '/api/v1/payments/process', '/api/v1/invoices/generate', '/api/v1/rates/current'],
            'dependency_graph' => [
                'src/services/payment/calculator.py' => ['src/api/routes/billing.py', 'src/workers/invoice_generator.py', 'src/services/notification/sender.py'],
                'src/utils/rate_helper.py' => ['src/services/payment/calculator.py', 'src/api/routes/billing.py'],
            ],
            'total_affected_files' => 7,
            'total_affected_services' => 4,
            'summary' => 'This PR modifies the core payment rate calculator which is a critical dependency for the billing API, invoice generator, and notification service. Changes propagate to 4 downstream services and 4 API endpoints. The payment-service area has had 3 P1 incidents in the last 90 days.',
        ]);

        RiskAssessment::updateOrCreate(['pull_request_id' => $pr1->id], [
            'risk_score' => 87,
            'risk_level' => 'critical',
            'historical_incidents' => [
                ['id' => 'INC-001', 'title' => 'Payment processing outage', 'severity' => 1, 'days_ago' => 12, 'relevance' => 'Direct match - same file modified'],
                ['id' => 'INC-002', 'title' => 'Billing API cascade failure', 'severity' => 1, 'days_ago' => 34, 'relevance' => 'Same subsystem'],
                ['id' => 'INC-003', 'title' => 'Payment service memory leak', 'severity' => 1, 'days_ago' => 58, 'relevance' => 'Pattern match - previous refactor caused OOM'],
            ],
            'contributing_factors' => [
                'Three P1 incidents in payment-service within 90 days',
                'Core calculator.py has been root cause 2 of 3 times',
                'PR touches 4 downstream services including billing pipeline',
                'Multi-currency refactor is a high-complexity change',
                'No canary deployment strategy configured',
                'Invoice generator has no circuit breaker',
            ],
            'recommendation' => 'BLOCK this deployment. The payment rate calculator has been the root cause of 3 P1 incidents in the last 90 days, with combined downtime of 551 minutes and 26 engineers paged. Recommend: (1) Deploy behind feature flag, (2) Add circuit breakers, (3) Canary deployment at 5% traffic, (4) Active SRE monitoring, (5) Rollback plan ready.',
        ]);

        DeploymentDecision::updateOrCreate(['pull_request_id' => $pr1->id], [
            'decision' => 'pending_review',
            'decided_by' => null,
            'has_concurrent_deploys' => false,
            'in_freeze_window' => false,
            'notified_oncall' => true,
            'notification_message' => 'CRITICAL: Risk score 87/100 for PR #47. 3 P1 incidents in this area within 90 days. Manual review required.',
            'decided_at' => null,
        ]);

        // PR 2: Low-risk documentation change
        $pr2 = PullRequest::updateOrCreate(
            ['github_pr_id' => 'demo-pr-48'],
            [
                'repo_full_name' => 'acme-corp/platform',
                'pr_number' => 48,
                'pr_title' => 'Update API documentation for v2 endpoints',
                'pr_author' => 'sarah-docs',
                'pr_url' => 'https://github.com/acme-corp/platform/pull/48',
                'base_branch' => 'main',
                'head_branch' => 'docs/api-v2-update',
                'files_changed' => 3,
                'additions' => 156,
                'deletions' => 42,
                'status' => 'approved',
            ]
        );

        BlastRadiusResult::updateOrCreate(['pull_request_id' => $pr2->id], [
            'affected_files' => ['docs/api/v2/endpoints.md', 'docs/api/v2/authentication.md', 'docs/CHANGELOG.md'],
            'affected_services' => [],
            'affected_endpoints' => [],
            'dependency_graph' => [],
            'total_affected_files' => 3,
            'total_affected_services' => 0,
            'summary' => 'Documentation-only change. No service code affected. No runtime risk.',
        ]);

        RiskAssessment::updateOrCreate(['pull_request_id' => $pr2->id], [
            'risk_score' => 5,
            'risk_level' => 'low',
            'historical_incidents' => [],
            'contributing_factors' => ['Documentation-only change', 'No executable code modified'],
            'recommendation' => 'Safe to deploy. Documentation files only.',
        ]);

        DeploymentDecision::updateOrCreate(['pull_request_id' => $pr2->id], [
            'decision' => 'approved',
            'decided_by' => 'DriftWatch AI',
            'has_concurrent_deploys' => false,
            'in_freeze_window' => false,
            'notified_oncall' => false,
            'notification_message' => null,
            'decided_at' => now()->subHours(2),
        ]);

        // PR 3: Medium-risk auth change
        $pr3 = PullRequest::updateOrCreate(
            ['github_pr_id' => 'demo-pr-49'],
            [
                'repo_full_name' => 'acme-corp/platform',
                'pr_number' => 49,
                'pr_title' => 'Add OAuth2 PKCE flow for mobile clients',
                'pr_author' => 'mike-security',
                'pr_url' => 'https://github.com/acme-corp/platform/pull/49',
                'base_branch' => 'main',
                'head_branch' => 'feature/oauth-pkce',
                'files_changed' => 5,
                'additions' => 312,
                'deletions' => 28,
                'status' => 'scored',
            ]
        );

        BlastRadiusResult::updateOrCreate(['pull_request_id' => $pr3->id], [
            'affected_files' => ['src/services/auth/oauth_handler.py', 'src/services/auth/token_service.py', 'src/api/routes/auth.py', 'tests/test_oauth.py', 'config/oauth_providers.yaml'],
            'affected_services' => ['auth-service', 'api-gateway', 'user-service'],
            'affected_endpoints' => ['/api/v1/auth/authorize', '/api/v1/auth/token', '/api/v1/auth/callback'],
            'dependency_graph' => ['src/services/auth/oauth_handler.py' => ['src/api/routes/auth.py', 'src/services/auth/token_service.py']],
            'total_affected_files' => 5,
            'total_affected_services' => 3,
            'summary' => 'Adds PKCE flow to OAuth2 handler. Affects auth service, API gateway, and user service. Auth-service had P2 incident 7 days ago.',
        ]);

        RiskAssessment::updateOrCreate(['pull_request_id' => $pr3->id], [
            'risk_score' => 52,
            'risk_level' => 'high',
            'historical_incidents' => [
                ['id' => 'INC-004', 'title' => 'Auth service timeout spike', 'severity' => 2, 'days_ago' => 7, 'relevance' => 'Same service area'],
            ],
            'contributing_factors' => ['Auth service had P2 incident 7 days ago', 'Changes affect authentication critical path', 'New OAuth flow adds complexity', 'API gateway routing changes affect all authenticated requests'],
            'recommendation' => 'Proceed with caution. Deploy during low-traffic window with enhanced monitoring. Feature flag recommended.',
        ]);

        DeploymentDecision::updateOrCreate(['pull_request_id' => $pr3->id], [
            'decision' => 'pending_review',
            'decided_by' => null,
            'has_concurrent_deploys' => false,
            'in_freeze_window' => false,
            'notified_oncall' => true,
            'notification_message' => 'Risk score 52/100 for PR #49. Auth service had recent P2. Review recommended.',
            'decided_at' => null,
        ]);

        // PR 4: Previously deployed, accurate prediction
        $pr4 = PullRequest::updateOrCreate(
            ['github_pr_id' => 'demo-pr-45'],
            [
                'repo_full_name' => 'acme-corp/platform',
                'pr_number' => 45,
                'pr_title' => 'Add Redis caching layer for product catalog',
                'pr_author' => 'jen-backend',
                'pr_url' => 'https://github.com/acme-corp/platform/pull/45',
                'base_branch' => 'main',
                'head_branch' => 'feature/product-cache',
                'files_changed' => 4,
                'additions' => 89,
                'deletions' => 12,
                'status' => 'deployed',
            ]
        );

        BlastRadiusResult::updateOrCreate(['pull_request_id' => $pr4->id], [
            'affected_files' => ['src/services/catalog/product_service.py', 'src/cache/redis_client.py', 'config/redis.yaml', 'tests/test_cache.py'],
            'affected_services' => ['product-catalog', 'search-service'],
            'affected_endpoints' => ['/api/v1/products', '/api/v1/products/{id}'],
            'dependency_graph' => ['src/services/catalog/product_service.py' => ['src/cache/redis_client.py']],
            'total_affected_files' => 4,
            'total_affected_services' => 2,
            'summary' => 'Adds Redis caching to product catalog lookups. Limited blast radius.',
        ]);

        RiskAssessment::updateOrCreate(['pull_request_id' => $pr4->id], [
            'risk_score' => 28,
            'risk_level' => 'medium',
            'historical_incidents' => [],
            'contributing_factors' => ['New caching layer adds invalidation complexity', 'Search depends on fresh catalog data'],
            'recommendation' => 'Low-moderate risk. Deploy with cache monitoring.',
        ]);

        DeploymentDecision::updateOrCreate(['pull_request_id' => $pr4->id], [
            'decision' => 'approved',
            'decided_by' => 'DriftWatch AI',
            'has_concurrent_deploys' => false,
            'in_freeze_window' => false,
            'notified_oncall' => false,
            'notification_message' => null,
            'decided_at' => now()->subDays(3),
        ]);

        DeploymentOutcome::updateOrCreate(['pull_request_id' => $pr4->id], [
            'predicted_risk_score' => 28,
            'incident_occurred' => false,
            'actual_severity' => null,
            'actual_affected_services' => ['product-catalog'],
            'prediction_accurate' => true,
            'post_mortem_notes' => 'Deployment completed without incident. Cache hit rate 94% within 30 minutes.',
        ]);

        // PR 5: Previously blocked (good catch)
        $pr5 = PullRequest::updateOrCreate(
            ['github_pr_id' => 'demo-pr-43'],
            [
                'repo_full_name' => 'acme-corp/platform',
                'pr_number' => 43,
                'pr_title' => 'Migrate session store from Redis to DynamoDB',
                'pr_author' => 'alex-dev',
                'pr_url' => 'https://github.com/acme-corp/platform/pull/43',
                'base_branch' => 'main',
                'head_branch' => 'infra/dynamo-sessions',
                'files_changed' => 8,
                'additions' => 445,
                'deletions' => 167,
                'status' => 'blocked',
            ]
        );

        BlastRadiusResult::updateOrCreate(['pull_request_id' => $pr5->id], [
            'affected_files' => ['src/services/auth/session_store.py', 'src/middleware/auth.py', 'src/config/session.py', 'src/services/auth/token_service.py', 'deploy/terraform/dynamodb.tf', 'config/aws.yaml', 'tests/test_sessions.py', 'tests/test_auth_flow.py'],
            'affected_services' => ['auth-service', 'user-service', 'api-gateway', 'checkout-service', 'admin-panel'],
            'affected_endpoints' => ['/api/v1/auth/*', '/api/v1/users/me', '/admin/*'],
            'dependency_graph' => ['src/services/auth/session_store.py' => ['src/middleware/auth.py', 'src/services/auth/token_service.py']],
            'total_affected_files' => 8,
            'total_affected_services' => 5,
            'summary' => 'Infrastructure migration affecting session storage for all authenticated services. Blast radius covers 5 services.',
        ]);

        RiskAssessment::updateOrCreate(['pull_request_id' => $pr5->id], [
            'risk_score' => 91,
            'risk_level' => 'critical',
            'historical_incidents' => [
                ['id' => 'INC-004', 'title' => 'Auth service timeout spike', 'severity' => 2, 'days_ago' => 7, 'relevance' => 'Same session store code path'],
            ],
            'contributing_factors' => ['Session store in critical path for ALL authenticated requests', 'Fundamental infrastructure change', 'Recent P2 in same code area', 'Affects 5 services', 'No dual-write migration strategy'],
            'recommendation' => 'BLOCK. High-risk infrastructure migration with no gradual rollout. Implement dual-write first.',
        ]);

        DeploymentDecision::updateOrCreate(['pull_request_id' => $pr5->id], [
            'decision' => 'blocked',
            'decided_by' => 'DriftWatch AI',
            'has_concurrent_deploys' => true,
            'in_freeze_window' => false,
            'notified_oncall' => true,
            'notification_message' => 'CRITICAL: Risk score 91/100 for PR #43. Blocked automatically.',
            'decided_at' => now()->subDays(5),
        ]);

        Log::info('[DemoDataSeeder] Seeded 5 demo pull requests with full pipeline data.');
    }
}
