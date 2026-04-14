<cfif structKeyExists(url, "reload") AND url.reload EQ "true"
    AND structKeyExists(url, "pw") AND url.pw EQ application.reloadPassword>
    <cfset applicationStop()>
    <cflocation url="/index.cfm" addtoken="false">
<cfelse>
    <cfabort>
</cfif>
