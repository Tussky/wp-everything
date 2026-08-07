# WordPress sandbox — Isaac Anderson

Agents request this sandbox by writing `wordpress-sandbox/request.json`; a root-owned runner processes it.

Example request:

```json
{"action":"start","slug":"isaac-anderson","reason":"WordPress is needed for this project"}
```

Result file: `wordpress-sandbox/result.json`
URL after startup: https://preview2.updraftailabs.com/live/isaac-anderson/
Local loopback: http://127.0.0.1:8919/
