> **<indirect>** permission means that there may be some non-specific restrictions on the request. An example of this is requests limited to user sessions only, but has no check for a specific permission or user group.

# Authentication Request Reference:

#### `POST` : `/auth/login`
Attempt login with an email and password.

**Permission:** `auth.accountmanaging.basic`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`email`|`string`|The user's registered email address.|No|
|`password`|`string`|The user's password.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|
|`sessionuid`|`string`|The user's session id.|On Success|
|`sessionkey`|`integer`|The user's session access token.|On Success|
|`refresh_interval`|`integer`|Interval time for sending a session refresh. Actual interval should always be less to guarantee session refresh.|On Success|

---

#### `POST` : `/auth/refresh`
Refreshes a user's session to keep it alive.

**Permission:** **<indirect>**

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/logout`
Invaldiate a user's session. (Logging them out)

**Permission:** `auth.accountmanaging.basic`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

--

#### `POST` : `/auth/changepassword`
Change the password of a user.

**Permission:** `auth.accountmanaging.basic`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`email`|`string`|The user's registered email address.|No|
|`password`|`string`|The user's current password.|No|
|`newpassword`|`string`|The new password which the user would like to change to.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/changeemail`
Change the email of a user.

**Permission:** `auth.accountmanaging.basic`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`email`|`string`|The user's current registered email address.|No|
|`password`|`string`|The user's current password.|No|
|`newemail`|`string`|The new email which the user would like to change to.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/getpermissions`
Get permissions of the current user by using their session info.

**Permission:** **<indirect>**

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`details`|`boolean`|Whether to return a more comprehensive list of permission data.|Yes|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|
|`permissions`|`array`|A list of permissions.|On Success|

---

#### `POST` : `/auth/admin/getuser`
Get all information and data of a user.

**Permission:** `auth.admin.getuser`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`email`|`string`|The email of the user to search for.|Yes|
|`uid`|`integer`|The user id of the user to search for.|Yes|
|`emailmatch`|`string`|Pattern matching against a user's email. `*` are wildcards.|Yes|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|
|`users`|`array`|A list of user data objects.|On Success|

---

#### `POST` : `/auth/admin/createuser`
Create a new user in the database.

**Permission:** `auth.admin.createuser`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`email`|`string`|The email for the user.|No|
|`password`|`integer`|The password for the user.|No|
|`groups`|`string`|The groups the user will be related to.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/admin/deleteuser`
Delete a user from the database.

**Permission:** `auth.admin.deleteuser`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`email`|`string`|The email of the user to delete.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/admin/addpermission`
Add a permission to the database.

**Permission:** `auth.admin.createpermission`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`name`|`string`|The name of the permission (Must match: `[a-zA-Z0-9_]+(\\.?)`) and length <= 512.|No|
|`displayname`|`string`|The display name for the permission.|No|
|`description`|`string`|The description for the permission.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/admin/deletepermission`
Delete a permission from the database.

**Permission:** `auth.admin.deletepermission`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`name`|`string`|The name of the permission.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/admin/getpermission`
Get information about a permission from the database.

**Permission:** `auth.admin.getpermission`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`name`|`string`|The name of the permission.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|
|`permissionname`|`string`|The name of the permission.|On Success|
|`pid`|`integer`|The id of the permission.|On Success|
|`dispalyname`|`string`|The display name of the permission.|On Success|
|`description`|`string`|The description of the permission.|On Success|

---

#### `POST` : `/auth/admin/addgroup`
Add a new group to the database.

**Permission:** `auth.admin.creategroup`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`name`|`string`|The name of the group (Must match: `[a-zA-Z0-9_]+(\\.?)`) and length <= 512.|No|
|`displayname`|`string`|The display name for the group.|No|
|`description`|`string`|The description for the group.|No|
|`priority`|`integer`|The priority of the permission, higher priority means permissions of this group override permissions of lower-priority groups.|Yes|
|`permissions`|`array`|A list of permissions this group contains.|Yes|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/admin/deletegroup`
Delete a group from the database.

**Permission:** `auth.admin.deletegroup`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`name`|`string`|The name of the group.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|

---

#### `POST` : `/auth/admin/getgroup`
Get information about a group from the database.

**Permission:** `auth.admin.getgroup`

**Request Payload:**

|Field|Type|Descrption|Optional|
|-----|----|----------|--------|
|`sessionuid`|`string`|The user's session id.|No|
|`sessionkey`|`integer`|The user's session access token.|No|
|`name`|`string`|The name of the permission.|No|
**Response Payload:**

|Field|Type|Descrption|Occurance|
|-----|----|----------|---------|
|`errors`|`array`|A list of potential errors, this is empty on no errors.|Always|
|`success`|`boolean`|This is only set and set to true when the request was a success.|On Success|
|`groupname`|`string`|The name of the group.|On Success|
|`priority`|`integer`|The priority of the group.|On Success|
|`permissions`|`array`|A list of permissions the group contains.|On Success|
|`gid`|`integer`|The id of the group.|On Success|
|`dispalyname`|`string`|The display name of the group.|On Success|
|`description`|`string`|The description of the group.|On Success|
