# digitalocean-spaces-helpers

Helper classes to interact with DigitalOcean spaces object storage using the AWS S3 SDK and Symfony HttpClient.

This library provides common functionality in regards to creating, deleting, and listing Spaces and objects. This library will also facilitate uploading and downloading objects.

## BAG\Spaces\Client

Main client class that can be used to manage spaces and objects.

```constructor(string $key, string $secret, string $region)```

| Name    | Type   | Description             |
|---------|--------|-------------------------|
| $key    | string | Spaces api key          |
| $secret | string | Spaces api secret       |
| $region | string | Spaces region, eg. nyc1 |