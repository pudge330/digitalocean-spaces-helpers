# DigitalOcean Spaces Helpers

Helper classes to interact with DigitalOcean spaces object storage using the AWS S3 SDK and Symfony HttpClient.

This library provides common functionality in regards to creating, deleting, and listing Spaces and objects. This library will also facilitate uploading and downloading objects.

This library is fairly new and the api for various methods could change in the upcoming future. I will do my best to document all changes and not introduce breaking changes without notice.

## Upcoming

- Usage examples.
- Discontinuing use of AWS S3 SDK and implementing fully with http api instead

## Classes

### BAG\Spaces\Client

Main client class that can be used to manage spaces and objects.

**Constants**

- ACL_PUBLIC = 'public-read'
- ACL_PRIVATE = 'private'

#### ```constructor($key, $secret, $region)```

| Name    | Type   | Optional| Description             |
|---------|--------|---------|-------------------------|
| $key    | string | false   | Spaces api key          |
| $secret | string | false   | Spaces api secret       |
| $region | string | false   | Spaces region, eg. nyc1 |

#### ```createSpace($name)```

Create space.

Returns true if space was created, false if it already existed and on failure.

| Name  | Type   | Optional| Description   |
|-------|--------|---------|---------------|
| $name | string | false   | Name of space |

#### ```deleteSpace($name)```

Delete space.

Returns false if space was deleted, false otherwise.

| Name  | Type   | Optional| Description   |
|-------|--------|---------|---------------|
| $name | string | true    | Name of space |

#### ```listSpaces($names = false)```

Get list of spaces.

Returns array of available spaces.

| Name   | Type | Optional| Description       |
|--------|------|---------|-------------------|
| $names | bool | true    | Return names only |

#### ```spaceExists($space)```

Check if space exists.

Returns true if space exists, false otherwise.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |

#### ```list($space, $keys = false, $arguments = [])```

Get list of objects.

Returns array of objects, false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $keys      | bool   | true    | Return keys only         |
| $arguments | array  | true    | Additional api arguments |

#### ```get($space, $key, $arguments = [])```

Get object.

Returns object, null if not found or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $key       | string | false   | Object key               |
| $arguments | array  | true    | Additional api arguments |

#### ```exists($space, $key)```

Check if object exists.

Returns true if object exists, false otherwise.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```getAcl($space, $key)```

Get an objects ACL.

Return string or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```setAcl($space, $key, $acl)```

Set an objects ACL.

Returns true or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |
| $acl   | string | false   | ACL value     |

#### ```upload($space, $key, $content, $public = false, $arguments = [])```

Upload content.

Return key as string if successful or false on failure.

| Name       | Type                                                   | Optional| Description              |
|------------|--------------------------------------------------------|---------|--------------------------|
| $space     | string                                                 | false   | Name of space            |
| $key       | string                                                 | false   | Object key               |
| $content   | string<br>resource<br>Psr\Http\Message\StreamInterface | false   | Content to add           |
| $public    | bool                                                   | true    | Object is public         |
| $arguments | array                                                  | true    | Additional api arguments |

#### ```uploadFile($space, $key, $file, $public = false, $arguments = [])```

Upload file.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```multipartUploadFile($space, $key, $file, $public = false, $arguments = [])```

Multipart file upload.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```download($space, $key, $destination)```

Downloads a file locally.

Returns true if file downloaded successfully or false on failure.

| Name         | Type   | Optional| Description      |
|--------------|--------|---------|------------------|
| $space       | string | false   | Name of space    |
| $key         | string | false   | Object key       |
| $destination | string | false   | File destination |

#### ```publicUrl($space, $key)```

Get object public URL.

Returns a URL string.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```presignedDownload($space, $key, $duration = '+5 minutes')```

Generate a presigned download URL.

Returns presigned download URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $space    | string | false   | Name of space                                 |
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```presignedUpload($space, $key, $duration = '+5 minutes')```

Generate a presigned upload URL.

Returns presigned upload URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $space    | string | false   | Name of space                                 |
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```delete($space, $key)```

Delete object.

Returns true on success, false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```baseKey($key)```

Get base key. Similar to PHP dirname().

Returns string.

| Name | Type   | Optional| Description |
|------|--------|---------|-------------|
| $key | string | false   | Object key  |

#### ```verifyRequest(&$request, $key, $default = [])```

Verify HTTP result has particular property.

| Name      | Type   | Optional| Description   |
|-----------|--------|---------|---------------|
| &$request | object | false   | Api result    |
| $key      | string | false   | Value key     |
| $default  | mixed  | true    | Default value |

#### ```createAuthorizationSignature($arguments)```

Create an authorization signature header.

Returns an array. First item being an array of headers use for api authorization, second item is the headers compiled to a string.

| Name       | Type   | Optional| Description                               |
|------------|--------|---------|-------------------------------------------|
| $arguments | array  | false   | Arguments required to construct signature |

#### ```getClient()```

Get S3Client instance.

Returns S3Client.

#### **static** ```createClient($key, $secret, $endpoint)```

Create S3Client.

Returns S3Client instance.

| Name      | Type   | Optional| Description       |
|-----------|--------|---------|-------------------|
| $key      | string | false   | Spaces api key    |
| $secret   | string | false   | Spaces api secret |
| $endpoint | string | false   | Api endpoint      |

#### **static** ```create($key, $secret, $endpoint)```

Create Spaces Client.

Returns Client instance.

| Name    | Type   | Optional| Description             |
|---------|--------|---------|-------------------------|
| $key    | string | false   | Spaces api key          |
| $secret | string | false   | Spaces api secret       |
| $region | string | false   | Spaces region, eg. nyc1 |

### BAG\Spaces\Space

Space class that can be used to manage a single space and its objects.

**Constants**

- ACL_PUBLIC = 'public-read'
- ACL_PRIVATE = 'private'

#### ```construct($space, $key, $secret, $region, $create = false)```

| Name    | Type   | Optional| Description                  |
|---------|--------|---------|------------------------------|
| $space  | string | false   | Name of space                |
| $key    | string | false   | Spaces api key               |
| $secret | string | false   | Spaces api secret            |
| $region | string | false   | Spaces region, eg. nyc1      |
| $create | string | true    | Create space if non-existent |

#### ```list($keys = false, $arguments = [])```

Get list of objects.

Returns array of objects, false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $keys      | bool   | true    | Return keys only         |
| $arguments | array  | true    | Additional api arguments |

#### ```get($key, $arguments = [])```

Get object.

Returns object, null if not found or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $key       | string | false   | Object key               |
| $arguments | array  | true    | Additional api arguments |

#### ```exists($key)```

Check if object exists.

Returns true if object exists, false otherwise.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```getAcl($key)```

Get an objects ACL.

Return string or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```setAcl($key, $acl)```

Set an objects ACL.

Returns true or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |
| $acl   | string | false   | ACL value     |

#### ```upload($key, $content, $public = false, $arguments = [])```

Upload content.

Return key as string if successful or false on failure.

| Name       | Type                                                   | Optional| Description              |
|------------|--------------------------------------------------------|---------|--------------------------|
| $key       | string                                                 | false   | Object key               |
| $content   | string<br>resource<br>Psr\Http\Message\StreamInterface | false   | Content to add           |
| $public    | bool                                                   | true    | Object is public         |
| $arguments | array                                                  | true    | Additional api arguments |

#### ```uploadFile($key, $file, $public = false, $arguments = [])```

Upload file.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```multipartUploadFile($key, $file, $public = false, $arguments = [])```

Multipart file upload.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```download($key, $destination)```

Downloads a file locally.

Returns true if file downloaded successfully or false on failure.

| Name         | Type   | Optional| Description      |
|--------------|--------|---------|------------------|
| $key         | string | false   | Object key       |
| $destination | string | false   | File destination |

#### ```publicUrl($key)```

Get object public URL.

Returns a URL string.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```presignedDownload($key, $duration = '+5 minutes')```

Generate a presigned download URL.

Returns presigned download URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```presignedUpload($key, $duration = '+5 minutes')```

Generate a presigned upload URL.

Returns presigned upload URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```delete($key)```

Delete object.

Returns true on success, false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```baseKey(string $key)```

Get base key. Similar to PHP dirname().

Returns string.

| Name | Type   | Optional| Description |
|------|--------|---------|-------------|
| $key | string | false   | Object key  |

#### ```getClient()```

Get Client instance.

Returns Client.

### BAG\Spaces\Authorization

Class that assists with generating http authorization headers.

#### ```construct($key, $secret)```

| Name    | Type   | Optional| Description                  |
|---------|--------|---------|------------------------------|
| $key    | string | false   | Spaces api key               |
| $secret | string | false   | Spaces api secret            |

#### ```create($arguments)```

Create authorization headers and return full set of headers generated.

Returns an array. First item being an array of headers use for api authorization, second item is the headers compiled to a string.

| Name       | Type  | Optional| Description       |
|------------|-------|---------|-------------------|
| $arguments | array | false   | Request arguments |
