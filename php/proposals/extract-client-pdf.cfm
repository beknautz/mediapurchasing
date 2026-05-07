<!---
    proposals/extract-client-pdf.cfm
    Accepts a multipart PDF upload, extracts all text via cfpdf,
    and returns JSON: { success: true, text: "...", pages: N }
    Called by admin/clients.php drag-drop PDF parser.
--->
<cfheader name="Content-Type" value="application/json; charset=utf-8">
<cfheader name="X-Content-Type-Options" value="nosniff">

<cftry>
    <!--- Must be a file upload --->
    <cfif NOT structKeyExists(form, "pdf") OR form.pdf EQ "">
        <cfoutput>{"success":false,"error":"No file received."}</cfoutput>
        <cfabort>
    </cfif>

    <!--- Save to a unique temp path --->
    <cfset tempDir  = getTempDirectory()>
    <cfset tempName = "cpdf_" & createUUID() & ".pdf">

    <cffile action="upload"
            fileField="pdf"
            destination="#tempDir#"
            nameConflict="makeUnique"
            accept="application/pdf,application/x-pdf">

    <cfset uploadedPath = cffile.serverDirectory & server.separator.file & cffile.serverFile>

    <!--- Verify it really is a PDF (magic bytes %PDF) --->
    <cffile action="readBinary" file="#uploadedPath#" variable="pdfBytes">
    <cfif left(toString(charsetDecode(pdfBytes, "utf-8")), 4) NEQ "%PDF">
        <cffile action="delete" file="#uploadedPath#">
        <cfoutput>{"success":false,"error":"Uploaded file is not a valid PDF."}</cfoutput>
        <cfabort>
    </cfif>

    <!--- Extract text from all pages --->
    <cfpdf action="extractText"
           source="#uploadedPath#"
           name="rawText"
           type="string">

    <!--- Count pages --->
    <cfpdf action="getInfo"
           source="#uploadedPath#"
           name="pdfInfo">

    <cfset pageCount = val(pdfInfo.totalPages ?: 0)>

    <!--- Clean up --->
    <cffile action="delete" file="#uploadedPath#">

    <!--- Return result --->
    <cfoutput>{"success":true,"text":#serializeJSON(rawText)#,"pages":#pageCount#}</cfoutput>

    <cfcatch type="any">
        <!--- Try to clean up if file was created --->
        <cftry>
            <cfif isDefined("uploadedPath") AND fileExists(uploadedPath)>
                <cffile action="delete" file="#uploadedPath#">
            </cfif>
            <cfcatch></cfcatch>
        </cftry>
        <cfoutput>{"success":false,"error":#serializeJSON(cfcatch.message)#}</cfoutput>
    </cfcatch>
</cftry>
