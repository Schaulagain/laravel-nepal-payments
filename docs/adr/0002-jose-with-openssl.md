# HBL's JOSE tokens use PHP's OpenSSL functions directly

`app/Payments/Jose/JoseEnvelope.php` signs and encrypts HBL's tokens with PHP's built-in OpenSSL functions, with a few lines of PHP for the PS256 padding.

## Considered options

- **`web-token/jwt-library`**: the usual choice. Without the GMP extension it does RSA in plain PHP, and one signature can take minutes. Hosts often don't have GMP.
- **OpenSSL directly**: fast everywhere PHP runs, at the cost of owning some crypto code. Its output was checked against the Node `jose` package in both directions, so any change to it needs that check again.
