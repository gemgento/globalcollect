Gemgento Global Collect Magento extension to make Global Collect compatible with Gemgento

The main features:
- Add API call to get saved credit card tokens.
- Allow bank codes to be set at website level instead of store level.
- Allow payment method to be set through SOAP API by checking for standard Magento variable names.
- Pass IP from Order.remote_ip attribute if it is available, otherwise default to request IP. This is because we use the API to create orders, so the request is not the real IP.
