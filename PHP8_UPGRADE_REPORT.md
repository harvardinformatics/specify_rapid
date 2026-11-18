# PHP 8 Upgrade Report: specify_rapid

Branch: `php8_upgrade` (from `transcription_enhancements` @ 9515afe)
Target: PHP 8.3 only (the branch no longer runs on PHP 5.5 or 7.x)
Status: code changes complete and committed; not yet run against a real Specify database or built as a container.

## 1. Decisions this work follows

| Question | Decision |
|---|---|
| PHP versions | PHP 8 only |
| Generated `huh_*.php` classes | Patched in place by script (the Druid generator is not in this repo) |
| mysqli error mode | Keep the pre-8.1 behaviour (`MYSQLI_REPORT_OFF`) |
| Warning clean-up scope | Every PHP file, including legacy pages |
| `User::logout()` | Make the session-secret invalidation work |
| `save()` insert check | Fix to `$this->PK()` |
| Container | Rebuild on `php:8.3-apache` |
| Git | Staged local commits, nothing pushed |

## 2. Commits

| Commit | Contents |
|---|---|
| `66faeba` | Generated model classes: fatal-error fixes |
| `17c1059` | Hand-written code: fatal-error fixes |
| `5d9dc2f` | Generated model classes: null-safe string arguments |
| `35dab4b` | Transcription app: warnings |
| `bb006d7` | Rapid entry, utilities, shared library: warnings |
| `362a7fb` | Legacy and test pages: warnings |
| `caa1eef` | Dockerfile and Apache site config |

## 3. What changed

### 3.1 Errors that were fatal on PHP 8

| Problem | Where | Fix |
|---|---|---|
| PHP 4-style constructors are not called | 173 `huh_*` classes; `User`, `DateWithPrecision`, `DateRangeWithPrecision` | Renamed to `__construct()` |
| `mysqli_error()` without an argument | 173 `huh_*` classes | `mysqli_error($connection)` |
| `mysql_escape_string()` removed | 173 `huh_*` classes; 9 calls in `class_lib.php` | `$connection->real_escape_string()` |
| `get_magic_quotes_gpc()` removed | 346 calls in `huh_*`; 2 in `class_lib.php` | Check removed; `addslashes`/`stripslashes` kept |
| Bare constants as array keys (`$pk[AgentID]`) | 789 places in `huh_*` | Quoted |
| Bare constants in composite-key `load()` (`if(AgentID != NULL ...)`) | 9 link-table classes | `$this->AgentID` |
| `utmzone!=null` | `class_lib.php` | `$utmzone!=null` |
| `"Error" + $e->getMessage()` | `imagehandler.php` | `.` |
| Constant and `null` literal passed to `bind_param()` | `class_lib.php` (`createImageSetFromLocalFile`) | Variables |
| `json_encode()` redeclared | `ajax_handler_jsonp.php` | `function_exists` guard; PHP's own `json_encode` is used |
| `mysqli_connect_errorno()` | `connection_library_template.php` | `mysqli_connect_errno()` |
| `$statement->$fetch` | `transcribe_handler.php` | `$statement->fetch()` |
| `explode()` argument order, `length()` | `druid_handler.php` | Corrected, `count()` |
| `fputs()`/`fclose()` on a report file that failed to open | `rapid.php` | Guarded |

mysqli error mode: `rapid.php`, `transcribe.php`, `utility.php`, `quickentry.php`, `displayimage.php`, `testimage.php` and `ajax_handler_jsonp.php` now call `mysqli_report(MYSQLI_REPORT_OFF)` before connecting. `ajax_handler.php`, `transcribe_logger.php` and `index_alternate.php` already set a mode. `transcribe_handler.php` already chose exceptions (`MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT`) and is unchanged.

### 3.2 Warnings and deprecations

- **Request and session values.** About 480 reads of `$_GET`, `$_POST`, `$_REQUEST` and `$_SESSION` now use `($_GET['x'] ?? '')`. The `@` suppression on those statements is removed (about 250 places).
- **Null passed to string functions (PHP 8.1).** Plain-variable arguments of `trim`, `strlen`, `substr`, `str_replace`, `preg_replace`, `preg_match`, `strpos`, `explode`, `urlencode`, `addslashes`, `stripslashes` and similar default to `''`: 8,805 places in the generated classes and about 135 in hand-written code. Places where static analysis showed the value is always a string were left alone.
- **By-reference arguments.** `bind_param()` no longer receives method-call results (`transcribe_lib.php`).
- **Undefined variables.** Initialised in `class_lib.php`, `ajax_handler.php`, `rapid.php`, `transcribe.php`, `transcribe_handler.php`, `displayimage.php`, `imagehandler.php`, `quickentry.php`, `index_alternate.php`, and in `loadArrayKeyValueSearch()` of the generated classes.
- **Null objects.** Reads from a batch position that has no file use `?->` (`transcribe.php`, `transcribe_handler.php`).
- **Dynamic property.** `User::$error` is declared.
- **`auto_detect_line_endings`.** Replaced in `rapid.php` by `fopenNormalizedLineEndings()`, which treats a lone carriage return as a line break.
- **`fgetcsv()`.** The escape argument is passed explicitly.

### 3.3 Container

- `Dockerfile` builds on `php:8.3-apache`, installs `mysqli` and `exif`, and writes PHP settings to `conf.d`.
- PHP errors are logged to stderr and not displayed (`display_errors = Off`). The old file's `display_error = stderr` line was a misspelt directive and had no effect.
- `etc/000-default` uses Apache 2.4 syntax (`Require all granted`) and a `SetHandler` block in place of `AddHandler php5-script .html`.

## 4. Behaviour changes to be aware of

These are intended, but they change what the application does.

1. **Logout.** `User::logout()` now replaces `huh_webuser.sessionsecret` with a new random value when the session holds the user's current valid ticket. The old query never ran. A random value is stored, not NULL, because `validateTicket()` treats a missing secret as an empty string. A stale session cannot log out a newer one.
2. **`save()` inserts.** Generated `save()` no longer throws for every insert. It throws only when no primary key is set.
3. **UTM locality detail.** With `$utmzone!=null`, a `localitydetail` row is inserted only when a UTM zone is supplied. On PHP 5 the mistyped test was always true, so a row was inserted for every new locality in that code path.
4. **Qualifier select.** The "cf." option is now selected when the value is `Compare` (`$cfs` was a typo for `$scf`) in `transcribe.php` and `quickentry.php`.
5. **Required-field validation message.** `field()` in `transcribe.php` and `quickentry.php` now emits the `validationMessage` attribute for required fields (`$validationMessage` was a typo).
6. **`showStructure()` collectors.** Collector names are reset for each matching item instead of accumulating across items when a barcode is not unique.
7. **JSONP responses.** `ajax_handler_jsonp.php` output comes from PHP's `json_encode`, so it is strict JSON.
8. **Generated form div id.** `div_$formnane` is now `div_$formname` in the generated form views.
9. **`druid_handler.php` multi-field keys.** The key-parsing branch now runs; it could not before.

## 5. Verification

| Check | Result |
|---|---|
| `php -l` on all 196 files, PHP 8.3, `E_ALL` | No errors or deprecations |
| PHPCompatibility, PHP 8.0 to 8.4 | No findings |
| PHPStan | No undefined-constant, constructor or removed-function findings; only the section 6 items remain |
| All 173 model classes instantiated on PHP 8.3 | No warnings |
| Runtime test | See below |

Runtime test: the pages ran on PHP 8.3 against a local MariaDB. The schema was generated from the model classes and seeded with one specimen, one image batch and one user. This is not the real Specify schema or data.
- Every `case` of every handler was requested by GET and POST, with and without parameters (about 320 requests), plus about 25 realistic requests: transcription saves for existing and new barcodes, rapid-entry saves, record and barcode lookups, batch completion, JSONP lookups.
- All PHP errors were logged, including `@`-suppressed ones.
- Final run: no warnings, notices or deprecations from repository code other than the items in section 6.
- Lines executed: `transcribe.php` 74%, `transcribe_handler.php` 61%, `transcribe_lib.php` 73%, `ajax_handler.php` 71%, `rapid.php` 61%, `utility.php` 62%, `quickentry.php` 73%, `class_lib.php` 46%, `imagehandler.php` 27%.

Not exercised: barcode scanning through php-zxing and Java, image-set creation, spreadsheet upload, password login through `Encryption.jar`, and anything that depends on real Specify data.

## 6. Known issues left as they are

These fail on any PHP version and are outside a compatibility change.

| Item | Detail |
|---|---|
| `transcribe.php?display=preparebatch` | Calls `doPrepareBatch()`, which is not defined |
| `ajax_handler.php` `returndistinctjsonidnamelimited` | Calls `keySelectDistinctJSONLimit()`, which the generated classes do not have for most tables |
| `ajax_handler.php` reference-work lookup (line 870) | Calls `keySelectDistinctJSONText1()`, which is not defined |
| Generated `delete()` | SQL is malformed (`WHERE  and X = ?`) and two variable names are mistyped |
| Generated `loadLinkedTo()` / `loadLinkedFrom*()` | Reference classes and functions that do not exist |
| `rapid.php` upload | Warns when the `uploads/` directory is missing |
| Array-valued request parameters | A request such as `?barcode[]=1` now ends in a `TypeError` (HTTP 500); PHP 5 only warned |
| `@` on `field()` / `select*()` calls | Left in `transcribe.php` and `quickentry.php`; nothing was suppressed in testing |
| `htdocs/test/` | Needs SimpleTest, which is not in the repo |

## 7. Before deploying

1. **Deployed `connection_library.php`** (not in git): check for the `mysqli_connect_errorno()` typo, and that it defines `JAVA_EXE`, `PHPINCPATH`, `$execstring` and `$vdateexecstring`.
2. **php-zxing** under `PHPINCPATH`: confirm the installed version runs on PHP 8.
3. **Schema.** `supporting/transcription_ddl.sql` is behind the code: it lacks `TR_USER_BATCH.last_accessed` and the `TR_USER_SETTING` table. Confirm the live database has them.
4. **Build and run the container.** The Dockerfile and site config have not been built.
5. **Smoke test on a copy of the database** with `error_reporting=E_ALL`: log in, log out, transcribe an existing and a new barcode, rapid-enter a record, set up an image batch, and upload a spreadsheet.
