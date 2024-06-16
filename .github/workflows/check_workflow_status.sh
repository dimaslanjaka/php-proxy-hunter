#!/bin/bash

# GitHub API URL
GITHUB_API_URL="https://api.github.com"

# Repository information
REPO_OWNER="dimaslanjaka"
REPO_NAME="php-proxy-hunter"
WORKFLOW_FILE_NAME="checker.yml"
BRANCH="master"

# Get the workflow ID based on the workflow file name
WORKFLOW_ID=$(curl -s -H "Authorization: token $GITHUB_TOKEN" \
  "$GITHUB_API_URL/repos/$REPO_OWNER/$REPO_NAME/actions/workflows" | \
  jq -r ".workflows[] | select(.path==\".github/workflows/$WORKFLOW_FILE_NAME\") | .id")

# Check the status of the latest run
check_workflow_status() {
  RUN_STATUS=$(curl -s -H "Authorization: token $GITHUB_TOKEN" \
    "$GITHUB_API_URL/repos/$REPO_OWNER/$REPO_NAME/actions/workflows/$WORKFLOW_ID/runs?branch=$BRANCH&status=in_progress" | \
    jq -r ".workflow_runs[0].status")

  if [ "$RUN_STATUS" == "in_progress" ]; then
    echo "Workflow is still running. Waiting..."
    return 1
  else
    echo "Workflow is not running."
    return 0
  fi
}

# Loop to wait for the workflow to complete
while ! check_workflow_status; do
  sleep 60
done
