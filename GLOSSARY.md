# Laravel Nepal Payments

A small store that takes payments through Nepali gateways and only treats a payment as paid once the gateway itself confirms it.

## Buying

**Order**:
What a customer is buying: one product at one price, in paisa.
_Avoid_: purchase, cart

**Payment**:
One attempt to pay for an order through one gateway. An order can have more than one if the first fails.
_Avoid_: transaction, charge

**Invoice**:
The record that an order was paid. Exactly one per completed payment.
_Avoid_: receipt, bill

**Paisa**:
The unit every amount is stored in. 100 paisa make one rupee.
_Avoid_: cents, minor units

## Gateways

**Gateway**:
An outside service that takes the customer's money: eSewa, Khalti or HBL.
_Avoid_: provider, processor, PSP

**Callback**:
The customer's browser coming back from the gateway to the store, carrying the gateway's answer.
_Avoid_: return URL, redirect (as a noun)

**Backend notification**:
A message the gateway's server sends straight to the store, without the customer's browser. Only HBL uses one.
_Avoid_: webhook, IPN

**Lookup**:
The store asking the gateway's API for the real status and amount of a payment.
_Avoid_: inquiry, status check, verify call

## Payment status

**Pending**:
The payment was started and the gateway hasn't confirmed an outcome yet.

**Completed**:
The gateway confirmed the full amount was paid. A completed payment never changes again.
_Avoid_: paid, success

**Failed**:
The gateway said no, the customer cancelled, or the amount didn't match.

**Reconcile**:
Running a lookup on payments that have stayed pending too long, usually because the customer closed the tab before the callback.
_Avoid_: sync, re-check job
