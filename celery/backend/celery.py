from __future__ import absolute_import
from celery import Celery

# For Celery 2.5
#app = Celery('mybackend')

# For Celery 3.x
app = Celery(include=[
	'backend.test_dns',
	'backend.test_har',
	'backend.test_tls'
])

app.conf.update(
	CELERY_TASK_RESULT_EXPIRES=120,
	CELERY_ACCEPT_CONTENT = [ 'json' ],
	CELERY_TASK_SERIALIZER = 'json',
	CELERY_RESULT_SERIALIZER = 'json',
	BROKER_URL = 'redis://',
	CELERY_RESULT_BACKEND = 'redis://',
	CELERY_DISABLE_RATE_LIMITS=True,
)


if __name__ == '__main__':
	app.start()
