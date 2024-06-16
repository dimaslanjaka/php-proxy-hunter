#!/bin/bash

# Function to display usage
usage() {
  echo "Usage: $0 --repo-owner=<repo_owner> --repo-name=<repo_name> --branch=<branch> --workflow-path=<workflow_path>"
  exit 1
}

# Parse arguments
for arg in "$@"; do
  case $arg in
    --repo-owner=*)
      REPO_OWNER="${arg#*=}"
      shift
      ;;
    --repo-name=*)
      REPO_NAME="${arg#*=}"
      shift
      ;;
    --branch=*)
      BRANCH="${arg#*=}"
      shift
      ;;
    --workflow-path=*)
      WORKFLOW_FILE_NAME="${arg#*=}"
      shift
      ;;
    *)
      usage
      ;;
  esac
done

# Validate arguments
if [ -z "$REPO_OWNER" ] || [ -z "$REPO_NAME" ] || [ -z "$BRANCH" ] || [ -z "$WORKFLOW_FILE_NAME" ]; then
  usage
fi

# GitHub API URL
GITHUB_API_URL="https://api.github.com"

# Get the workflow ID based on the workflow file name
WORKFLOW_ID=$(curl -s -H "Authorization: token $GITHUB_TOKEN" \
  "$GITHUB_API_URL/repos/$REPO_OWNER/$REPO_NAME/actions/workflows" | \
  jq -r ".workflows[] | select(.path==\"$WORKFLOW_FILE_NAME\") | .id")

if [ -z "$WORKFLOW_ID" ]; then
  echo "Workflow not found!"
  exit 1
fi

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
