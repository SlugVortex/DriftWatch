$files = @(
    "app\Http\Controllers\DriftWatchController.php",
    "app\Models\PullRequest.php",
    "database\migrations\2026_03_02_191714_create_pull_requests_table.php",
    "resources\views\driftwatch\show.blade.php",
    "resources\views\layouts\app.blade.php"
)

$outputFile = "pr_page_context.txt"
$projectDir = "c:\xampp\htdocs\DriftWatch"
Set-Location $projectDir

# Generate file tree
cmd /c tree /A > tree_dirs.txt

# Start writing to output
Set-Content $outputFile -Value "=========================================================="
Add-Content $outputFile -Value "DriftWatch Project Tree (Directories)"
Add-Content $outputFile -Value "=========================================================="
Get-Content tree_dirs.txt | Add-Content $outputFile
Add-Content $outputFile -Value ""

foreach ($file in $files) {
    if (Test-Path $file) {
        Add-Content $outputFile -Value "=========================================================="
        Add-Content $outputFile -Value "FILE: $file"
        Add-Content $outputFile -Value "=========================================================="
        Get-Content $file -Encoding UTF8 | Add-Content $outputFile
        Add-Content $outputFile -Value ""
        Add-Content $outputFile -Value ""
    } else {
        Add-Content $outputFile -Value "FILE NOT FOUND: $file"
    }
}

Write-Host "Created $outputFile successfully."
