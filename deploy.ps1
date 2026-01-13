# deploy.ps1
# Usage: .\deploy.ps1 -ProjectId YOUR_PROJECT_ID -Region YOUR_REGION

param (
    [Parameter(Mandatory=$true)]
    [string]$ProjectId,
    
    [Parameter(Mandatory=$false)]
    [string]$Region = "us-central1",
    
    [Parameter(Mandatory=$false)]
    [string]$ServiceName = "retail-pos"
)

Write-Host "Checking for gcloud CLI..." -ForegroundColor Cyan
if (!(Get-Command gcloud -ErrorAction SilentlyContinue)) {
    Write-Error "gcloud CLI not found. Please install the Google Cloud SDK and ensure it's in your PATH."
    exit 1
}

Write-Host "Setting project to $ProjectId..." -ForegroundColor Cyan
gcloud config set project $ProjectId

Write-Host "Building and pushing image using Google Cloud Build..." -ForegroundColor Cyan
gcloud builds submit --tag "gcr.io/$ProjectId/$ServiceName"

Write-Host "Deploying to Cloud Run..." -ForegroundColor Cyan
gcloud run deploy $ServiceName `
    --image "gcr.io/$ProjectId/$ServiceName" `
    --platform managed `
    --region $Region `
    --allow-unauthenticated

Write-Host "Deployment complete!" -ForegroundColor Green
