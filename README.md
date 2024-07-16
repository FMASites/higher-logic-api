# higher-logic-api
Laravel Service Provider that handles API interactions with Higher Logic's products (currently limited to Real Magnet).

Potentially helpful resources:
* [Higher Logic Real Magnet REST API documentation](https://support.higherlogic.com/hc/en-us/articles/360032691632-REST-API)
* [Laravel package development documentation](https://laravel.com/docs/packages)
* [Laravel service providers documentation](https://laravel.com/docs/providers)
* [Guzzle documentation](https://docs.guzzlephp.org/en/stable/), and in particular, the bit about [testing Guzzle clients](https://docs.guzzlephp.org/en/stable/testing.html)
* A very old, but full of good hints [GitHub repo for real-magnet-sdk](https://github.com/jjpmann/real-magnet-sdk)

## Add the package to the application
First, because this package isn't on [Packagist](https://packagist.org/), a config entry needs to be made in the
`composer.json` file to instruct Composer where to find it:

```php
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/FMASites/higher-logic-api"
        }
    ]
}
```

The package can be added to your application using the Composer CLI:
```
composer require fmasites/higher-logic-api
```

Or, it can be added to the project by directly updating the `composer.json` file to include the package. If the app is
built in a Docker container, this is more likely what you want to do:
```
{
    "name": "your-project-etc",
    "type": "project",
    ...
    "require": {
        ...
        "fmasites/higher-logic-api": "*",
        ...
    },
```
And then running `composer update` (if building locally), or `make composer-update` if building in a Docker container.
It is very likely that this will trigger a request for a GitHub authentication token (for private repos).

### Update the .env file
Higher Logic provides a number of APIs for their various services, one of which is the Real Magnet API around which this
package is focused. Thus, the initial `.env` variables are prefixed with "RealMagnet." Future updates may expand into
other APIs which may require their own credentials.

```
REALMAGNET_USERNAME=theRealMagnetAPIUsername
REALMAGNET_PASSWORD=theRealMagnetAPIPassword
```

## Usage
The service provider creates a singleton object for the Laravel service container to use. When it is created, the
credentials are used to automatically authenticate and be ready for use.

Sample usage:

```php
use FMASites\HigherLogicApi\RealMagnet;

class SomeController extends Controller
{    
    public function exampleStoringUserOptIn(Request $request, RealMagnet $api): JsonResponse
    {
        $validatedFields = $request->validate([
           // All the fields
        ]);

        // Add to Higher Logic email group
        $higherLogicUserId = $api->upsertRecipient($validatedFields['email']);

        if ($higherLogicUserId && $api->addToGroup($higherLogicUserId, $validatedFields['higherlogic_group_id'])) {
            return response()->json();
        }

        // Status other than 200 triggers error in form
        return response()->json(null, 418);
    }
}

```
