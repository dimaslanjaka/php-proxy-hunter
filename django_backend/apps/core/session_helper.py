from django.shortcuts import render, redirect


def set_session(request, key, value):
    request.session[key] = value


def get_session(request, key, default=None):
    return request.session.get(key, default)


def delete_session(request, key):
    if key in request.session:
        del request.session[key]
