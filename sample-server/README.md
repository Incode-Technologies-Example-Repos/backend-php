# Example Token Server Using PHP

## Endpoints

- GET `/start`: Call Incode's `/omni/start` API to create an Incode session which will include a token in the JSON response.  This token can be shared with Incode SDK client apps to do token based initialization, which is a best practice.

- GET `/onboarding-url`: Calls incodes `/omni/start` and then with the token calls `/0/omni/onboarding-url` to retrieve the unique onboarding-url for the newly created session.

It can receive the optional query parameter `redirectUrl` to set where to redirect the user at the end of the flow.

- POST `/webhook`: Example webhook that reads the json data and return it back a response, from here you could fetch scores or OCR data when the status is ONBOARDING_FINISHED

## Secure Credential Handling
We highly recommend to follow the 0 rule for your implementations, where all sensitive calls to incode's endpoints are done in the backend, keeping your apikey protected and just returning a `token` with the user session to the frontend.

Within this sample you will find the only call to a `/omni/` endpoint we recommend for you to have, it requires the usage of the `apikey`, all further calls must be done using only the generated `token` and be addresed to the `/0/omni` endpoints. 

## Prerequisites
This sample uses Composer and GuzzleHttp with some methods dependent on [PHP7+](https://www.php.net/downloads.php)

## Local Development

### Environment
Rename `sample.env` file to `.env` adding your subscription information:

```env
API_URL=https://demo-api.incodesmile.com
API_KEY=you-api-key
CLIENT_ID=your-client-id
FLOW_ID=Flow Id from your Incode dashboard.
```

### Run Localy
Install composer and run composer update to get all dependencies
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar update
```

Expose the PHP script using PHP-CLI
```bash
php -S 0.0.0.0:3000  
```

The server will accept petitions on `http://localhost:3000/`


### Expose the server to the internet for frontend testing with ngrok
For your frontend to properly work in tandem with this server on your mobile phone for testing, you will need a public url with proper SSL configured, by far the easiest way to acchieve this with an ngrok account properly configured on your computer. You can visit `https://ngrok.com` to make a free account and do a quick setup.

In another shell expose the server to internet through your computer ngrok account:

```bash
ngrok http 3000
```

Open the `Forwarding` adress in a web browser. The URL should look similar to this: `https://466c-47-152-68-211.ngrok-free.app`.

Now you should be able to visit the following routes to receive the associated payloads:
1. `https://yourforwardingurl.app/start`
2. `https://yourforwardingurl.app/onboarding-url`
3. `https://yourforwardingurl.app/onboarding-url?redirectionUrl=https%3A%2F%2Fexample.com%2F`

## Webhook
We provide an example on how to read the data we send in the webhook calls, from here you could
fetch scores and OCR data, what you do with that is up to you.

To recreate the call and the format of the data sent by Incode you can use the following script:

```bash
curl --location 'https://yourforwardingurl.app/webhook' \
--header 'Content-Type: application/json' \
--data '{
    "interviewId": "<interviewId>",
    "onboardingStatus": "ONBOARDING_FINISHED",
    "clientId": "<clientId>",
    "flowId": "<flowId>"
}'
```

## Dependencies

* **php7++**: A popular general-purpose scripting language that is especially suited to web development. .
* **phpdotenv**: Used to access environment variables.
* **ngrok**: Unified ingress platform used to expose your local server to the internet.
* **guzzlehttp**: Used to make http calls.
