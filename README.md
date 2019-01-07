# Using the Jeeb plugin for Easy Digital Downloads

## Prerequisites

* Wordpress 3.5 and Easy Digital Downloads 2.4.2
* Last Version Tested: Wordpress 4.9 and Easy Digital Downloads 2.4.2

You must have a Jeeb merchant account to use this plugin.

## Installation
* Go to Wordpress Plugins > Add New
* Click Upload Plugin Zip File
* Upload the zipped Jeeb plugin file, you can find it in [latest release](https://github.com/gdhar67/Jeeb.EasyDigitalDownloads/releases) and click "Upload Now"
* Go to Installed Plugins
* Activate the "Jeeb Easy Digital Downloads (EDD) - Bitcoin Payment Gateway"
 
## Configuration
1. Go to Downloads -> Settings -> Payment Gateways.
2. Select "Jeeb Payments" as Payment Gateway. Be sure to enable it.
3. Get your signature of your merchant account from Jeeb.
4. Scrol down and enter your API Key from step 3.
5. Select your network: livenet for real bitcoin, testnet for test bitcoin. Check the box if you want testnet(Mainly used for debbugging and testing purposes). 
6. Set a Base currency(it usually should be the currency of your store) and Target Currency(It is a multi-select option. You can choose any cryptocurrency from the listed options).
7. Set the language of the payment page (you can set Auto-Select to auto detecting manner).
8. Click save and close.
