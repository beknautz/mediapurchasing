<!---
    proposals/extract-client-pdf.cfm
    Accepts a multipart PDF upload, extracts all text via cfpdf,
    returns JSON: { success: true, text: "...", pages: N }
--->
<cfcontent type="application/json; charset=utf-8">
<cfsetting showdebugoutput="false" enablecfoutputonly="true">

<cftry>

    <!--- Must have a file field --->
    <cfif NOT structKeyExists(form, "pdf")>
        <cfoutput>{"success":false,"error":"No file received."}</cfoutput>
        <cfabort>
    </cfif>

    <!--- Upload to CF temp directory --->
    <cffile action="upload"
            fileField="pdf"
            destination="#getTempDirectory()#"
            nameConflict="makeUnique">

    <cfset uploadedPath = cffile.serverDirectory & "/" & cffile.serverFile>

    <!--- Extract text --->
    <cfpdf action="extractText"
           source="#uploadedPath#"
           name="rawText"
           type="string">

    <!--- Get page count separately, safely --->
    <cfset pageCount = 0>
    <cftry>
        <cfpdf action="getInfo" source="#uploadedPath#" name="pdfInfo">
        <cfset pageCount = val(pdfInfo.totalPages)>
        <cfcatch></cfcatch>
    </cftry>

    <!--- Clean up temp file --->
    <cftry>
        <cffile action="delete" file="#uploadedPath#">
        <cfcatch></cfcatch>
    </cftry>

    <cfoutput>{"success":true,"text":#serializeJSON(rawText)#,"pages":#pageCount#}</cfoutput>

    <cfcatch type="any">
        <!--- Clean up if possible --->
        <cftry>
            <cfif isDefined("uploadedPath") AND fileExists(uploadedPath)>
                <cffile action="delete" file="#uploadedPath#">
            </cfif>
            <cfcatch></cfcatch>
        </cftry>
        <cfoutput>{"success":false,"error":#serializeJSON(cfcatch.message & " (" & cfcatch.type & ")")#}</cfoutput>
    </cfcatch>
</cftry>
