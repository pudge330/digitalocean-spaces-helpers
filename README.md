# digitalocean-spaces-helpers

Helper classes to interact with DigitalOcean spaces object storage using the AWS S3 SDK and Symfony HttpClient.

This library provides common functionality in regards to creating, deleting, and listing Spaces and objects. This library will also facilitate uploading and downloading objects.

## BAG\Spaces\Client

Main client class that can be used to manage spaces and objects.

**Constants**

- ACL_PUBLIC = 'public-read'
- ACL_PRIVATE = 'private'

### ```constructor(string $key, string $secret, string $region)```

| Name    | Type   | Optional| Description             |
|---------|--------|---------|-------------------------|
| $key    | string | false   | Spaces api key          |
| $secret | string | false   | Spaces api secret       |
| $region | string | false   | Spaces region, eg. nyc1 |

### ```createSpace(string $name)```

Create space.

Returns true if space was created, false if it already existed and on failure.

| Name  | Type   | Optional| Description   |
|-------|--------|---------|---------------|
| $name | string | false   | Name of space |

### ```deleteSpace(string $name)```

Delete space.

Returns false if space was deleted, false otherwise.

| Name  | Type   | Optional| Description   |
|-------|--------|---------|---------------|
| $name | string | false   | Name of space |

### ```listSpaces(bool $names = false)```

Get list of spaces.

Returns list of available spaces.

| Name   | Type | Optional| Description       |
|------- |------|---------|-------------------|
| $names | bool | true    | Return names only |

### ```spaceExists(string)```

Check if space exists.

Returns true if space exists, false otherwise.

| Name   | Type   | Optional| Description   |
|------- |--------|---------|---------------|
| $space | string | false   | Name of space |

## BAG\Spaces\Space

**Constants**

- ACL_PUBLIC = 'public-read'
- ACL_PRIVATE = 'private'

Space class that can be used to manage a single space and its objects.

```construct(string $space, string $key, string $secret, string $region, bool $create = false)```

| Name    | Type   | Optional| Description                  |
|---------|--------|---------|------------------------------|
| $space  | string | false   | Name of space                |
| $key    | string | false   | Spaces api key               |
| $secret | string | false   | Spaces api secret            |
| $region | string | false   | Spaces region, eg. nyc1      |
| $create | string | true    | Create space if non-existent |
