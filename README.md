## Payment Requests

> This application is a WIP, please don't expect it to stand up to proper use :)

### Overview
BIP70 payment requests alleviate some of the issues of requesting payment with Bitcoin. It addresses problems such as: address reuse, allowing customers to specify a refund address, and also allows for small memo's to be attached for informational purposes. 

Since BIP70 implementations are customer facing and involve payment, there is a need to verify the details included in such messages. This could be examining a client/servers locale settings for memos, identifying outright errors, or 'developer-speak' messages sent to clients. 

As such, this app caters for two use cases. Developers of server-side applications which generate payment requests, and developers of mobile applications which will respond to payment requests.

The end result should be a comprehensive report of each payment request, outlining issues which may affect the payments success.


### Scope:

 - Create payment requests
 - Examine a clients response to payment requests (the application will provide QR's + URLs for to serve payment requests)
 - Test a server which generates its own requests (the user provides a payment request URL, which the application will fulfill) *not implemented

### Design

 - A minimal website to create and serve payment requests.
 - ZMQ: Master and CoinWorker's work to take new payment requests, and look for fulfilling transactions over the peer-to-peer network.
 - Tiny bitcoin nodes - Each CoinWorker has it's own connection to the network. They request transaction-relay, to hear about newly broadcast transactions. 
 - Websockets - The following actions are reported to the details page:

     - client.gotRequest: A bitcoin wallet has downloaded the PaymentRequest
     - client.gotPayment: The wallet has responded to the Request with a Payment message, containing transactions.
     - tx.partial: A transaction partially fulfilling the PaymentRequest was observed.
     - tx.complete: A transaction was observed which fulfilled ALL requirements of the request.
     - request.complete: A transaction was found in a block, which fulfilled the request, or, included a transaction mentioned in a Payment message
