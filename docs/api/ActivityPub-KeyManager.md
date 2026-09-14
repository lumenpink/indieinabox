# KeyManager
**Namespace:** `Indieinabox\ActivityPub`

Class KeyManager

Handles generation, storage, and retrieval of RSA key pairs for ActivityPub signing.

## Properties

### `private PDO $db`

@var PDO Database connection.

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

KeyManager constructor.

@param ?PDO $db Optional PDO connection. If null, Database::getDb() is used.

### ensureKeys()
`public function ensureKeys(string $keyId = 'main-key'): void`

Ensures an RSA key pair exists for signing ActivityPub payloads.
If keys are missing, generates a new 2048-bit RSA pair and saves them in the database.

@param string $keyId The key identifier (defaults to 'main-key').
@return void

### getPublicKey()
`public function getPublicKey(string $keyId = 'main-key'): ?string`

Retrieves the public key PEM string for a given key ID.

@param string $keyId The key identifier (defaults to 'main-key').
@return ?string

### getPrivateKey()
`public function getPrivateKey(string $keyId = 'main-key'): ?string`

Retrieves the private key PEM string for a given key ID.

@param string $keyId The key identifier (defaults to 'main-key').
@return ?string
