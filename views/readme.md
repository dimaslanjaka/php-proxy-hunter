# Views

default template

```twig
{% extends "template.twig" %}

{% set site_title %}SITE TITLE{% endset %}
{% set page_title %}PAGE TITLE{% endset %}
{% block endhead %}HTML BEFORE </head>{% endblock endhead %}
{% block body %}HTML INSIDE <body/>{% endblock body %}
{% block endbody %}HTML BEFORE </body>{% endblock endbody %}
```

# Assets

Location resource assets in **/views/assets** will be compiled using a rollup to the folder **/public/assets**

# Debug

To debug in twig, use

```twig
<pre><code>{{ views_debug|json_encode(constant('JSON_PRETTY_PRINT')) }}</code></pre>
```