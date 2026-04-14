<!---
    Twilio Inbound SMS Webhook
    Configure in Twilio Console: Phone Numbers > (your number) > Messaging > Webhook
    URL: https://yourdomain.com/api/twilio_sms.cfm
    HTTP Method: POST
    This endpoint MUST be publicly accessible (no auth).

    For security, optionally validate Twilio's X-Twilio-Signature header.
--->
<cfheader statuscode="200" statustext="OK">
<cfcontent type="text/xml">

<cfset smsSvc = new components.SMSService()>

<cftry>
    <!--- Optional: validate Twilio signature --->
    <!---
    <cfset authToken = application.settings.twilio_auth_token ?: "">
    <cfset twilioSig = getHTTPRequestData().headers["X-Twilio-Signature"] ?: "">
    ... validate HMAC-SHA1 here if desired
    --->

    <cfset postData = {}>
    <cfloop list="#structKeyList(form)#" index="k">
        <cfset postData[k] = form[k]>
    </cfloop>

    <cfset smsSvc.processInbound(postData)>

    <!--- TwiML response: empty or auto-reply --->
    <?xml version="1.0" encoding="UTF-8"?>
    <Response></Response>

<cfcatch type="any">
    <cflog file="mediapurchasing_errors" type="error"
           text="Twilio SMS webhook error: #cfcatch.message#">
    <?xml version="1.0" encoding="UTF-8"?>
    <Response></Response>
</cfcatch>
</cftry>
