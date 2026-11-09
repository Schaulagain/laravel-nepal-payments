# Only the gateway confirms a payment

A payment becomes completed only after the app has checked it with the gateway: eSewa's signature, or a lookup for Khalti and HBL. Whatever arrives in the callback URL is treated as a hint about which payment to check. This means an extra API call on every callback, and a payment can stay pending while a gateway is down, which is why the reconcile job exists. In return, a customer can't mark an order paid by editing a URL.
