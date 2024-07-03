# higher-logic-api
Laravel Service Provider that handles API requests to HigherLogic from a Laravel Application

Publish the config file:
```
php artisan vendor:publish --provider="HigherLogicApi\HigherLogicApiServiceProvider" --tag=config
```

Add the following entries to the .env file in your Laravel projects:
- HIGHERLOGIC_USERNAME=your_username
- HIGHERLOGIC_PASSWORD=your_password


```php
use FMASites\HigherLogicApi;

class SomeController extends Controller
{
    public function storeGetUpdatesForm(Request $request, HigherLogicApi $api): JsonResponse
    {
        $validatedFields = $request->validate([
            'form_id' => 'required',
            'email' => 'required|email',
            'event_name' => 'nullable',
            'higherlogic_group_id' => 'required',
            'consent' => 'required',
            'recaptcha_response' => ['required', new ReCaptchaRule($request)],
        ]);

        FormSubmission::quickSave($validatedFields);

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