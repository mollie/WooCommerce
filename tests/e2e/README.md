
### Setup E2E tests
In your test environment
- Import the products
- Check the language of the site, must be English unless specified
- Update the env with url and credentials
- VSCode has a playwright plugin
- Install and activate basic auth plugin: https://github.com/WP-API/Basic-Auth
- Run ngrok to expose the site and be able to test the webhooks
```
$ npx playwright test
```

