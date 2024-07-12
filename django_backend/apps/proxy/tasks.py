# django_backend/apps/proxy/tasks.py

from celery import shared_task


@shared_task
def doCrawl(task_id):
    # Your crawling logic here
    print(f"Starting crawl for task: {task_id}")
    # Simulate a long-running task
    import time
    time.sleep(10)
    print(f"Finished crawl for task: {task_id}")


@shared_task
def debug_task():
    print('Debug task executed.')
    return 'Task completed successfully'
