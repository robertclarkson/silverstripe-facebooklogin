= Facebook Connector =

Adds a facebook login to the silverstripe site.

Add the following to your config:

```
FacebookControllerExtension:
  app_id: 'app-id'
  api_secret: 'app-secret'
  sync_member_details: true
  create_member: true
```

