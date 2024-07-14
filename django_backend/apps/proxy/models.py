import json
from django.db import models


class Proxy(models.Model):
    id = models.BigAutoField(primary_key=True)
    proxy = models.TextField(unique=True)
    latency = models.TextField(blank=True, null=True)
    last_check = models.TextField(blank=True, null=True)
    type = models.TextField(blank=True, null=True)
    region = models.TextField(blank=True, null=True)
    city = models.TextField(blank=True, null=True)
    country = models.TextField(blank=True, null=True)
    timezone = models.TextField(blank=True, null=True)
    latitude = models.TextField(blank=True, null=True)
    longitude = models.TextField(blank=True, null=True)
    anonymity = models.TextField(blank=True, null=True)
    https = models.TextField(blank=True, null=True)
    status = models.TextField(blank=True, null=True)
    private = models.TextField(blank=True, null=True)
    lang = models.TextField(blank=True, null=True)
    useragent = models.TextField(blank=True, null=True)
    webgl_vendor = models.TextField(blank=True, null=True)
    webgl_renderer = models.TextField(blank=True, null=True)
    browser_vendor = models.TextField(blank=True, null=True)
    username = models.TextField(blank=True, null=True)
    password = models.TextField(blank=True, null=True)

    class Meta:
        db_table = 'proxies'
        # app_label = 'django_backend.apps.proxy'

    def to_json(self):
        """
        Return a JSON string representation of the object.
        """
        return json.dumps({
            'id': self.id,
            'proxy': self.proxy,
            'latency': self.latency,
            'last_check': self.last_check,
            'type': self.type,
            'region': self.region,
            'city': self.city,
            'country': self.country,
            'timezone': self.timezone,
            'latitude': self.latitude,
            'longitude': self.longitude,
            'anonymity': self.anonymity,
            'https': self.https,
            'status': self.status,
            'private': self.private,
            'lang': self.lang,
            'useragent': self.useragent,
            'webgl_vendor': self.webgl_vendor,
            'webgl_renderer': self.webgl_renderer,
            'browser_vendor': self.browser_vendor,
            'username': self.username,
            'password': self.password
        })


class Meta(models.Model):
    key = models.TextField(primary_key=True)
    value = models.TextField()

    class Meta:
        db_table = 'meta'
        # app_label = 'django_backend.apps.proxy'
