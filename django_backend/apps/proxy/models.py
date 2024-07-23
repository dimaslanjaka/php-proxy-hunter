import json
from django.db import models
from django.utils.encoding import force_str


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

    def save(self, *args, **kwargs):
        self.proxy = force_str(self.proxy)
        self.latency = force_str(self.latency) if self.latency else self.latency
        self.last_check = (
            force_str(self.last_check) if self.last_check else self.last_check
        )
        self.type = force_str(self.type) if self.type else self.type
        self.region = force_str(self.region) if self.region else self.region
        self.city = force_str(self.city) if self.city else self.city
        self.country = force_str(self.country) if self.country else self.country
        self.timezone = force_str(self.timezone) if self.timezone else self.timezone
        self.latitude = force_str(self.latitude) if self.latitude else self.latitude
        self.longitude = force_str(self.longitude) if self.longitude else self.longitude
        self.anonymity = force_str(self.anonymity) if self.anonymity else self.anonymity
        self.https = force_str(self.https) if self.https else self.https
        self.status = force_str(self.status) if self.status else self.status
        self.private = force_str(self.private) if self.private else self.private
        self.lang = force_str(self.lang) if self.lang else self.lang
        self.useragent = force_str(self.useragent) if self.useragent else self.useragent
        self.webgl_vendor = (
            force_str(self.webgl_vendor) if self.webgl_vendor else self.webgl_vendor
        )
        self.webgl_renderer = (
            force_str(self.webgl_renderer)
            if self.webgl_renderer
            else self.webgl_renderer
        )
        self.browser_vendor = (
            force_str(self.browser_vendor)
            if self.browser_vendor
            else self.browser_vendor
        )
        self.username = force_str(self.username) if self.username else self.username
        self.password = force_str(self.password) if self.password else self.password
        super(Proxy, self).save(*args, **kwargs)

    class Meta:
        db_table = "proxies"
        # app_label = 'django_backend.apps.proxy'

    def to_insert_sql(self):
        fields = [
            "proxy",
            "latency",
            "last_check",
            "type",
            "region",
            "city",
            "country",
            "timezone",
            "latitude",
            "longitude",
            "anonymity",
            "https",
            "status",
            "private",
            "lang",
            "useragent",
            "webgl_vendor",
            "webgl_renderer",
            "browser_vendor",
            "username",
            "password",
        ]
        values = [getattr(self, field) for field in fields]
        values_str = ", ".join([f"'{v}'" if v is not None else "NULL" for v in values])
        fields_str = ", ".join(fields)
        sql = f"INSERT INTO proxies ({fields_str}) VALUES ({values_str});"
        return sql

    def to_update_sql(self):
        fields = [
            "proxy",
            "latency",
            "last_check",
            "type",
            "region",
            "city",
            "country",
            "timezone",
            "latitude",
            "longitude",
            "anonymity",
            "https",
            "status",
            "private",
            "lang",
            "useragent",
            "webgl_vendor",
            "webgl_renderer",
            "browser_vendor",
            "username",
            "password",
        ]
        updates = ", ".join(
            [
                (
                    f"{field} = '{getattr(self, field)}'"
                    if getattr(self, field) is not None
                    else f"{field} = NULL"
                )
                for field in fields
            ]
        )
        sql = f"UPDATE proxies SET {updates} WHERE proxy = {self.proxy};"
        return sql

    def to_delete_sql(self):
        sql = f"DELETE FROM proxies WHERE proxy = {self.proxy};"
        return sql

    def to_json(self):
        """
        Return a JSON string representation of the object.
        """
        return json.dumps(
            {
                "id": self.id,
                "proxy": self.proxy,
                "latency": self.latency,
                "last_check": self.last_check,
                "type": self.type,
                "region": self.region,
                "city": self.city,
                "country": self.country,
                "timezone": self.timezone,
                "latitude": self.latitude,
                "longitude": self.longitude,
                "anonymity": self.anonymity,
                "https": self.https,
                "status": self.status,
                "private": self.private,
                "lang": self.lang,
                "useragent": self.useragent,
                "webgl_vendor": self.webgl_vendor,
                "webgl_renderer": self.webgl_renderer,
                "browser_vendor": self.browser_vendor,
                "username": self.username,
                "password": self.password,
            }
        )


class Meta(models.Model):
    key = models.TextField(primary_key=True)
    value = models.TextField()

    def save(self, *args, **kwargs):
        self.key = force_str(self.key)
        self.value = force_str(self.value)
        super(Meta, self).save(*args, **kwargs)

    class Meta:
        db_table = "meta"
        # app_label = 'django_backend.apps.proxy'
