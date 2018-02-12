## How requests works ##

Every reqests should have the format:
```
https://api.zurvan-labs.net/module/action?/arg1?/arg2?
```


|||
| :-------- | -------- |
| `module` | The module to send the request to. |
| `action?` | Optional. This is used to specify an action for a module. |
| `arg1?` | Optional argument for an action. |
| `arg2?` | Optional argument for an action. |

-----

Above you have a very basic request and should be suitable for GET requests only. But it's not this simple. Most of the time you have to specify extra data with the requests. This is sent as a payload with the header through the request. The data should be formatted as valid JSON. If the data is not valid, the API will return an error of any sort.

## Modules ##
Modules are what makes the magic flow in the API. The core of the API simply manages the modules and figures out which one to execute and whom to not execute. Basically, modules holds the actual functionality of the API and eahc module can be looked at some kind of category for a API functionality.

### Module `auth`: ###
Main path: `https://api.zurvan-labs.net/auth`
#### Description ####
The auth module is the module you would use if you want to access the authentication services. It has a variety of functionality like login, session management and some admin functions.

#### Actions: ####
**LOGIN** (`https://api.zurvan-labs.net/auth/login`):

**METHOD:** POST

**REQUEST PAYLOAD:**
```
{
	"email": "email@address.com",
    "password": "userpassword",
    "expire": true
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `email` | **string** | The user's email. |
| `password` | **string** | The user's password. |
| `expire` | **boolean** | If true, the user's session will expire, if false the user's session will not expire. This option is optional and is true by default. |

**RETURNS:**
```
{
	"errors": [<possible errors>],
    "success": true,
    "sessionkey": "user_session_key_64_chars",
    "sessionuid": 1337
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `errors` | **array** | Potential errors. |
| `success` | **boolean** | This is only returned if the login was a success. |
| `sessionkey` | **boolean** | The user's new session key. |
| `sessionuid` | **boolean** | The user's user id. |

**DESCRIPTION**

Used for logging into the authentication system and creating a session.

-----

**CHANGEPASSWORD** (`https://api.zurvan-labs.net/auth/changepassword`):

**METHOD:** POST

**REQUEST PAYLOAD:**
```
{
	"email": "email@address.com",
    "password": "oldpassword",
    "newpassword": "the_new_password"
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `email` | **string** | The user's email. |
| `password` | **string** | The user's old password. |
| `newpassword` | **string** | The user's new password. |

**RETURNS:**
```
{
	"errors": [<possible errors>],
    "success": true
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `errors` | **array** | Potential errors. |
| `success` | **boolean** | This only if the password was changed. |

**DESCRIPTION**

Used for changing a user's password.

-----

**ADMIN -> CREATEUSER** (`https://api.zurvan-labs.net/auth/admin/createuser`):

**METHOD:** POST

**REQUEST PAYLOAD:**
```
{
	"sessionkey": "user_session_key_64_chars",
    "sessionuid": 1337,
    "email": "newuser@mailaddress.com",
    "password": "newuserpassword",
    "canlogin": true
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `sessionkey` | **string** | The admin's session key. |
| `sesionuid` | **integer** | The admin's user id. |
| `email` | **string** | The email of the new user. |
| `password` | **string** | The password of the new user. |
| `canlogin` | **boolean** | If true, the user is allowed to login, if false the user is not allowed to login. This is optional and true by default. |

**RETURNS:**
```
{
	"errors": [<possible errors>],
    "success": true
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `errors` | **array** | Potential errors. |
| `success` | **boolean** | This is only returned of the user was created. |

**DESCRIPTION**

Used for creating a new user in the authentication database.

-----

**ADMIN -> DELETEUSER** (`https://api.zurvan-labs.net/auth/admin/deleteuser`):

**METHOD:** POST

**REQUEST PAYLOAD:**
```
{
	"sessionkey": "user_session_key_64_chars",
    "sessionuid": 1337,
    "email": "newuser@mailaddress.com"
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `sessionkey` | **string** | The admin's session key. |
| `sesionuid` | **integer** | The admin's user id. |
| `email` | **string** | The email of the user to delete. |

**RETURNS:**
```
{
	"errors": [<possible errors>],
    "success": true
}
```
| FIELD | TYPE | DESCRIPTION |
| -------- | -------- | -------- |
| `errors` | **array** | Potential errors. |
| `success` | **boolean** | This is only returned if the user was deleted. |

**DESCRIPTION**

Used for deleting a user from the authentication database.
