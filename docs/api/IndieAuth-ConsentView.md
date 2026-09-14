# ConsentView
**Namespace:** `Indieinabox\IndieAuth`

Class ConsentView

Renders the HTML consent/authorization screen and JSON error responses for IndieAuth.

## Methods

### renderLoginForm()
`public static function renderLoginForm(Indieinabox\Site $site, array $params, ?string $error = null): void`

Renders the HTML login and authorization consent form.

@param Site $site Site metadata.
@param array<string, string> $params Authorization request parameters.
@param string|null $error Optional error message.
@return void

### renderError()
`public static function renderError(int $code, string $message): void`

Sends a JSON error response.

@param int $code HTTP status code.
@param string $message Response message.
@return void
