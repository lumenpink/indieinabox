# InstallServiceInterface
**Namespace:** `Indieinabox\Services\Contracts`

Interface InstallServiceInterface

Defines the contract for initializing the Indieinabox instance:
configuring database paths, executing schema migrations, saving initial settings,
seeding default content, and running initial static builds.

## Methods

### install()
`abstract public function install(array $params = []): array`

Executes the installation workflow atomically in sequence.

@param array<string, mixed> $params Installation parameters (db_path, data_dir, sitename, fqdn, author, password, lang, contentdir, build).
@return array<string, mixed> Installation results (success, sitename, fqdn, author, contentdir, db_path, data_dir, lang, password, generated_password, built).
