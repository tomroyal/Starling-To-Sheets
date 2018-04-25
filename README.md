# Starling-To-Sheets

Exports last month's Starling Bank (https://github.com/starlingbank) transactions to a new tab on a Google Sheet - a way of keeping statements in Google Drive, essentially.

To use this you'll need to follow this tutorial to create a client_secret.json:

https://developers.google.com/sheets/api/quickstart/php

.. and to create a Starling Bank Developers token for your account with read transactions privileges:

https://developer.starlingbank.com/token/list

.. and, finally, to create a blank Sheet in Google Drive and grab its ID from the URL.

When run, the script grabs all transactions in the last calendar month (so if run on May 3, all transactions in April), and writes them to a named tab on the sheet. 
