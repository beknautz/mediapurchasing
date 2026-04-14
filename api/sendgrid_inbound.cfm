<!---
    SendGrid Inbound Parse Webhook
    Configure in SendGrid: Settings > Inbound Parse > Add Host & URL
    URL: https://yourdomain.com/api/sendgrid_inbound.cfm
    POST body: multipart/form-data with envelope, headers, from, to, subject, text, html, etc.
    This endpoint MUST be publicly accessible (no auth).
--->
<cfheader statuscode="200" statustext="OK">
<cfcontent type="text/plain">

<cfset emailSvc = new components.EmailService()>

<cftry>
    <!--- Collect all POST fields --->
    <cfset postData = {}>
    <cfloop list="#structKeyList(form)#" index="k">
        <cfset postData[k] = form[k]>
    </cfloop>

    <!--- Also capture file attachments if present --->
    <cfif structKeyExists(form, "attachments") AND val(form.attachments) GT 0>
        <cfset postData["attachment_count"] = form.attachments>
        <!--- Process each attachment file --->
        <cfloop from="1" to="#form.attachments#" index="i">
            <cfset attKey = "attachment#i#">
            <cfif len(form[attKey] ?: "")>
                <cfset uploadDir = expandPath("/uploads/bills/")>
                <cftry>
                    <cffile action="upload" filefield="#attKey#" destination="#uploadDir#" nameconflict="makeunique" result="attResult">
                    <cfset postData["attachment#i#_path"] = "/uploads/bills/#attResult.serverFile#">
                <cfcatch><!--- skip failed uploads ---></cfcatch>
                </cftry>
            </cfif>
        </cfloop>
    </cfif>

    <cfset result = emailSvc.processInbound(postData)>

    <!--- Write simple acknowledgment --->
    OK

<cfcatch type="any">
    <cflog file="mediapurchasing_errors" type="error"
           text="SendGrid inbound webhook error: #cfcatch.message#">
    OK<!--- Always return 200 to SendGrid to prevent retry loops --->
</cfcatch>
</cftry>
