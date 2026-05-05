<!---
    proposals/agency-agreement-pdf.cfm
    Adobe ColdFusion 2023 — generates a downloadable Agency Agreement PDF.

    Called from PHP:
        /proposals/agency-agreement-pdf.cfm?id=42
--->
<cfparam name="url.id" default="0" type="integer">

<!--- ── Load agreement ───────────────────────────────────────────────────── --->
<cfquery name="qAg" datasource="mediapurchasing">
    SELECT *
    FROM   agency_agreements
    WHERE  id = <cfqueryparam value="#val(url.id)#" cfsqltype="cf_sql_integer">
    LIMIT  1
</cfquery>

<cfif qAg.recordCount eq 0>
    <cfheader statusCode="404" statusText="Not Found">
    <cfoutput>Agreement not found.</cfoutput>
    <cfabort>
</cfif>

<!--- ── Load branding settings ───────────────────────────────────────────── --->
<cfquery name="qBrand" datasource="mediapurchasing">
    SELECT setting_key, setting_value
    FROM   workflow_settings
    WHERE  setting_key IN (
        'agency_name','agency_dba','agency_address',
        'agency_city_state_zip','agency_phone',
        'agency_signer_name','agency_signer_title','agency_logo_url'
    )
</cfquery>

<cfset b = {
    name:         "Enigma, Inc. DBA Enigma Marketing",
    dba:          "Enigma Marketing",
    address:      "3601 W Washington STE 130",
    city_state_zip: "Yakima, WA 98903",
    phone:        "509-452-3733",
    signer_name:  "Duane Gordon",
    signer_title: "Managing Partner",
    logo_url:     ""
}>
<cfloop query="qBrand">
    <cfset b[setting_key] = setting_value>
</cfloop>

<!--- Normalise DBA --->
<cfset dba = len(trim(b.dba)) ? b.dba : b.name>

<!--- ── Parse JSON fields ────────────────────────────────────────────────── --->
<cfset services     = []>
<cfset paySchedule  = []>
<cfset budgetCats   = []>

<cfif len(trim(qAg.services_json))>
    <cftry>
        <cfset services = deserializeJSON(qAg.services_json)>
        <cfcatch><cfset services = []></cfcatch>
    </cftry>
</cfif>
<cfif len(trim(qAg.payment_schedule_json))>
    <cftry>
        <cfset paySchedule = deserializeJSON(qAg.payment_schedule_json)>
        <cfcatch><cfset paySchedule = []></cfcatch>
    </cftry>
</cfif>
<cfif len(trim(qAg.budget_json))>
    <cftry>
        <cfset budgetCats = deserializeJSON(qAg.budget_json)>
        <cfcatch><cfset budgetCats = []></cfcatch>
    </cftry>
</cfif>

<!--- ── Helpers ──────────────────────────────────────────────────────────── --->
<cffunction name="fmtMoney" output="false" returntype="string">
    <cfargument name="v" type="any" default="0">
    <cfreturn "$" & numberFormat(val(arguments.v), "9,999.00")>
</cffunction>

<cffunction name="fmtDate" output="false" returntype="string">
    <cfargument name="d" type="string" default="">
    <cfif len(trim(arguments.d))>
        <cftry>
            <cfreturn dateFormat(parseDateTime(arguments.d), "MMMM D, YYYY")>
            <cfcatch><cfreturn arguments.d></cfcatch>
        </cftry>
    </cfif>
    <cfreturn "">
</cffunction>

<!--- ── Derived values ───────────────────────────────────────────────────── --->
<cfset clientName  = len(trim(qAg.client_name))  ? qAg.client_name  : "CLIENT NAME">
<cfset clientRep   = len(trim(qAg.client_representative)) ? qAg.client_representative : "____________________________">
<cfset contractStart = len(trim(qAg.contract_start)) ? fmtDate(qAg.contract_start) : "Upon signing">
<cfset contractEnd   = len(trim(qAg.contract_end))   ? fmtDate(qAg.contract_end)   : "12 months after signing">

<cfset budgetTotal = 0>
<cfloop array="#budgetCats#" index="cat">
    <cfif isArray(cat.rows)>
        <cfloop array="#cat.rows#" index="row">
            <cfset budgetTotal += val(row.amount)>
        </cfloop>
    </cfif>
</cfloop>

<cfset anyBudget = false>
<cfloop array="#budgetCats#" index="cat">
    <cfif isArray(cat.rows) and arrayLen(cat.rows) gt 0>
        <cfset anyBudget = true>
    </cfif>
</cfloop>

<!--- ── PDF filename ─────────────────────────────────────────────────────── --->
<cfset safeTitle = reReplace(qAg.title, "[^A-Za-z0-9_\-\s]", "", "all")>
<cfset safeTitle = reReplace(trim(safeTitle), "\s+", "-", "all")>
<cfset pdfFilename = "Agency-Agreement-" & safeTitle & ".pdf">

<!--- ── Shared CSS ───────────────────────────────────────────────────────── --->
<cfsavecontent variable="css">
body         { font-family: Georgia, "Times New Roman", serif; font-size: 10.5pt; color: #111111; line-height: 1.6; margin: 0; padding: 0; }
h1           { font-size: 20pt; font-weight: bold; text-align: center; color: #1a1a2e; margin: 8px 0 4px; }
p            { margin: 0 0 7px; }
ol, ul       { margin: 0 0 7px; padding-left: 22px; }
li           { margin-bottom: 3px; }
strong       { font-weight: bold; }
em           { font-style: italic; }
.subtitle    { text-align: center; font-style: italic; color: #555555; font-size: 9pt; margin-bottom: 14px; }
.heading     { font-size: 10pt; font-weight: bold; color: #1a1a2e; text-transform: uppercase;
               letter-spacing: 0.06em; border-bottom: 1.5pt solid #1a1a2e;
               padding-bottom: 3px; margin: 16px 0 6px; }
/* Letterhead */
.lh-wrap     { border-bottom: 3pt solid #1a1a2e; padding-bottom: 10px; margin-bottom: 14px; overflow: hidden; }
.lh-name     { font-size: 18pt; font-weight: bold; color: #1a1a2e; }
.lh-dba      { font-size: 9pt; color: #555555; font-style: italic; }
.lh-contact  { font-size: 8.5pt; color: #444444; line-height: 1.5; text-align: right; float: right; }
/* Parties box */
table.parties   { width: 100%; border-collapse: collapse; border: 1pt solid #dde2f0;
                  font-size: 9pt; margin: 10px 0 14px; background: #f4f6fb; }
table.parties td { padding: 10px 12px; vertical-align: top; border-right: 1pt solid #dde2f0; }
table.parties td:last-child { border-right: none; }
.party-lbl      { font-size: 7pt; font-weight: bold; text-transform: uppercase;
                  letter-spacing: 0.07em; color: #888888; margin-bottom: 3px; }
.party-name     { font-weight: bold; font-size: 10pt; color: #1a1a2e; }
.party-det      { color: #555555; line-height: 1.45; }
/* Payment table */
table.pay    { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9.5pt; }
table.pay th { background: #1a1a2e; color: #ffffff; padding: 5px 10px; text-align: left; font-size: 9pt; }
table.pay td { padding: 4px 10px; border-bottom: 1pt solid #e5e5e5; }
table.pay .pay-tot td { background: #1a1a2e; color: #ffffff; font-weight: bold; }
/* Budget table */
table.bud    { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9.5pt; }
table.bud th { background: #1a1a2e; color: #ffffff; padding: 5px 10px; text-align: left; font-size: 9pt; }
table.bud td { padding: 4px 10px; border-bottom: 1pt solid #e5e5e5; }
table.bud .bud-sub td { background: #edf0f8; font-weight: bold; border-top: 1.5pt solid #bbbbcc; }
table.bud .bud-tot td { background: #1a1a2e; color: #ffffff; font-weight: bold; font-size: 10pt; }
/* Signature blocks */
.sig-wrap    { margin-top: 24px; }
table.sig    { width: 100%; border-collapse: collapse; }
table.sig td { padding: 0 20px 0 0; vertical-align: top; width: 50%; }
table.sig td:last-child { padding: 0 0 0 20px; }
.sig-line    { border-bottom: 1pt solid #444444; height: 30px; margin-bottom: 4px; }
.sig-lbl     { font-size: 8.5pt; color: #555555; }
.sig-name    { font-size: 9.5pt; font-weight: bold; color: #111111; }
table.sig-info { width: 100%; border-collapse: collapse; border-top: 1pt solid #dddddd;
                 margin-top: 10px; font-size: 9pt; color: #444444; }
table.sig-info td { padding: 8px 20px 0 0; vertical-align: top; width: 50%; }
table.sig-info td:last-child { padding: 8px 0 0 20px; }
</cfsavecontent>

<!--- ── LETTERHEAD MACRO (used on each section) ──────────────────────────── --->
<cfsavecontent variable="lhHtml">
<cfoutput>
<div class="lh-wrap">
    <cfif len(trim(b.logo_url))>
        <div style="float:right;font-size:8.5pt;color:##444444;line-height:1.5;text-align:right;">
            #b.address#<br>#b.city_state_zip#<br>#b.phone#
        </div>
        <div style="float:left;">
            <img src="#b.logo_url#" style="max-height:70px;max-width:220px;vertical-align:bottom;">
        </div>
    <cfelse>
        <div style="float:right;font-size:8.5pt;color:##444444;line-height:1.5;text-align:right;">
            #b.address#<br>#b.city_state_zip#<br>#b.phone#
        </div>
        <div style="float:left;">
            <div class="lh-name">#b.name#</div>
            <cfif len(trim(b.dba)) and b.dba neq b.name>
                <div class="lh-dba">DBA #b.dba#</div>
            </cfif>
        </div>
    </cfif>
    <div style="clear:both;"></div>
</div>
</cfoutput>
</cfsavecontent>

<!--- ════════════════════════════════════════════════
      GENERATE PDF
      ════════════════════════════════════════════════ --->
<cfdocument format="PDF" name="pdfBytes"
            pageType="letter" unit="in"
            marginTop="0.7" marginBottom="0.7"
            marginLeft="0.75" marginRight="0.75"
            localURL="yes">

    <!--- ══ PAGE 1 — AGREEMENT COVER ══ --->
    <cfdocumentsection>
    <html><head><style>#css#</style></head><body>
    <cfoutput>

    #lhHtml#

    <h1>Agency Agreement</h1>
    <div class="subtitle">Marketing Services Contract</div>

    <p>This agreement, by and between <strong>#b.name#</strong> (&ldquo;Agency&rdquo;) and
    <strong>#clientName#</strong> (&ldquo;Client&rdquo;), is a legally binding agreement.
    Agency agrees to provide marketing services described herein in exchange for payment
    from Client in accordance with the terms of this agreement.</p>

    <!--- Parties info box --->
    <table class="parties">
    <tr>
        <td>
            <div class="party-lbl">Agency</div>
            <div class="party-name">#b.name#</div>
            <div class="party-det">#b.address#<br>#b.city_state_zip#<br>#b.phone#</div>
        </td>
        <td>
            <div class="party-lbl">Client</div>
            <div class="party-name">#clientName#</div>
            <div class="party-det">
                <cfif len(trim(qAg.client_representative))>
                    #qAg.client_representative#<cfif len(trim(qAg.client_title))>, #qAg.client_title#</cfif><br>
                </cfif>
                <cfif len(trim(qAg.client_address))>#qAg.client_address#<br></cfif>
                <cfif len(trim(qAg.client_city_state_zip))>#qAg.client_city_state_zip#<br></cfif>
                #qAg.client_phone#
            </div>
        </td>
        <td>
            <div class="party-lbl">Contract Period</div>
            <div class="party-det">
                <strong>Start:</strong><br>#contractStart#<br>
                <strong>End:</strong><br>#contractEnd#
            </div>
        </td>
    </tr>
    </table>

    <div class="heading">Appointment</div>
    <p>Client agrees to retain Agency as a provider of the marketing services provided
    below. Agency agrees to provide these services pursuant to the terms of this
    agreement.</p>
    <p><em>*Should Client require additional marketing and advertising services from Agency,
    both parties shall negotiate terms for those services, and attach such terms as an
    addendum to this agreement.</em></p>

    <div class="heading">Marketing Services</div>
    <ol>
        <cfloop array="#services#" index="svcItem">
            <li>#svcItem#</li>
        </cfloop>
    </ol>

    <div class="heading">Pricing</div>
    <cfif arrayLen(paySchedule) gt 0>
        <table class="pay">
        <tr>
            <th>Payment</th>
            <th style="text-align:right;">Amount</th>
            <th>Due</th>
        </tr>
        <cfset rowNum = 0>
        <cfloop array="#paySchedule#" index="pItem">
            <cfset rowNum++>
            <cfset rowBg = (rowNum mod 2 eq 0) ? "background:##f7f8fc;" : "">
            <tr style="#rowBg#">
                <td>#pItem.label#</td>
                <td style="text-align:right;font-weight:bold;">#fmtMoney(pItem.amount)#</td>
                <td>#pItem.due#</td>
            </tr>
        </cfloop>
        <tr class="pay-tot">
            <td><strong>Total</strong></td>
            <td style="text-align:right;">#fmtMoney(qAg.total_amount)#</td>
            <td></td>
        </tr>
        </table>
    <cfelseif val(qAg.total_amount) gt 0>
        <p>Client agrees to pay <strong>#fmtMoney(qAg.total_amount)#</strong> for the marketing services provided.</p>
    <cfelse>
        <p>Pricing to be determined per addendum.</p>
    </cfif>

    <!--- Budget Breakdown --->
    <cfif anyBudget>
    <div class="heading">Budget Breakdown</div>
    <table class="bud">
    <tr>
        <th>Category</th>
        <th>Outlet / Platform</th>
        <th style="text-align:right;">Amount</th>
    </tr>
    <cfloop array="#budgetCats#" index="cat">
        <cfif isArray(cat.rows) and arrayLen(cat.rows) gt 0>
            <cfset catTotal = 0>
            <cfset ri = 0>
            <cfloop array="#cat.rows#" index="row">
                <cfset catTotal += val(row.amount)>
                <cfset ri++>
                <cfset rowBg = (ri mod 2 eq 0) ? "background:##f7f8fc;" : "">
                <tr style="#rowBg#">
                    <cfif ri eq 1>
                        <td rowspan="#arrayLen(cat.rows)#" style="font-weight:600;vertical-align:top;padding-top:6px;">
                            #cat.label#
                        </td>
                    </cfif>
                    <td>#row.name#</td>
                    <td style="text-align:right;">#fmtMoney(row.amount)#</td>
                </tr>
            </cfloop>
            <tr class="bud-sub">
                <td colspan="2">#cat.label# Subtotal</td>
                <td style="text-align:right;">#fmtMoney(catTotal)#</td>
            </tr>
        </cfif>
    </cfloop>
    <tr class="bud-tot">
        <td colspan="2">Total Budget</td>
        <td style="text-align:right;">#fmtMoney(budgetTotal)#</td>
    </tr>
    </table>
    </cfif>

    <cfif val(qAg.contingency_monthly) gt 0>
    <p><strong>Contingency Funds Recommendation:</strong>
    #fmtMoney(qAg.contingency_monthly)# per month be set aside for additional services
    or unforeseen expenses. These funds to be discussed prior to services rendered and
    agreed upon with Agency Managing Partner and Client.</p>
    </cfif>

    <p><strong>Mileage:</strong> Travel to be paid at #fmtMoney(qAg.mileage_rate)# per mile
    if travel is necessary outside of Yakima city limits, billed starting from Agency&rsquo;s
    physical location. Travel time to be billed at half the hourly rate of
    #fmtMoney(qAg.hourly_rate)# per hour (Non-profit rate).</p>

    <cfif len(trim(qAg.additional_notes))>
    <p>#replace(xmlFormat(qAg.additional_notes), chr(10), "<br>", "all")#</p>
    </cfif>

    </cfoutput>
    </body></html>
    </cfdocumentsection>

    <!--- ══ PAGE 2 — TERMS & SIGNATURES ══ --->
    <cfdocumentsection>
    <html><head><style>#css#</style></head><body>
    <cfoutput>

    #lhHtml#

    <div class="heading" style="margin-top:0;">Terms</div>

    <p><strong>Contract Duration:</strong></p>
    <ul>
        <li><strong>Start:</strong> #contractStart#</li>
        <li><strong>End:</strong> #contractEnd#</li>
    </ul>
    <p><em>*Note: The contract can be updated with a specific date if required by the Client.
    At the end of the term, renegotiation or an addendum will be created for future
    services or partnership.</em></p>

    <p><strong>Payment &amp; Billing:</strong></p>
    <ul>
        <li>The Client acknowledges and agrees to the payment schedule outlined above.</li>
        <li>Additional services outside the original project scope, including extra page designs
            or additional features, will incur extra charges.</li>
        <li>An invoice will be provided to #clientName# based on the aforementioned payment
            installments.</li>
        <li>Payment is due upon receipt.</li>
    </ul>

    <p><strong>Scope of Work &amp; Collaboration:</strong></p>
    <ul>
        <li>The scope provided by the Client serves as a guideline and does not limit Agency
            and #clientName# from collaborating on other projects or materials as needed.</li>
        <li>Agency will receive partnership/sponsorship recognition on all printed and electronic
            materials created and maintained by Agency.</li>
    </ul>

    <p><strong>Exclusions &amp; Additional Costs:</strong></p>
    <ul>
        <li>Third-party costs (billed separately, estimates available upon request):
            Printing, media placements, travel expenses, and third-party production.</li>
        <li>Purchased images: $25 each.</li>
    </ul>

    <p><strong>Cancellation Policy:</strong></p>
    <p>If the project is canceled after the start, 50% of the total fee is non-refundable.
    If canceled after designs are delivered, the full amount is due.</p>
    <p>Upon cancellation, the Client owns all completed work in its current state, excluding
    native mechanical files, raw video, or raw photography, which remain the property of
    #dba# unless transferred under a separate agreement with applicable fees.</p>

    <div class="heading">Acknowledgment and Agreement</div>
    <p>By signing below, I acknowledge that I have reviewed and agreed to the terms and
    conditions outlined in this contract.</p>

    <!--- Signature block --->
    <div class="sig-wrap">
    <table class="sig">
    <tr>
        <td>
            <div class="sig-line"></div>
            <div class="sig-name">#b.signer_name#</div>
            <div class="sig-lbl">Name &mdash; #b.name#</div>
            <div class="sig-lbl">Title: #b.signer_title#</div>
            <div class="sig-lbl" style="margin-top:6px;">Date: ______________________</div>
            <div class="sig-lbl" style="margin-top:4px;">Signature: _________________</div>
        </td>
        <td>
            <div class="sig-line"></div>
            <div class="sig-name">#qAg.client_representative#</div>
            <div class="sig-lbl">Name &mdash; #clientName#</div>
            <div class="sig-lbl">Title: #len(trim(qAg.client_title)) ? qAg.client_title : "______________________"#</div>
            <div class="sig-lbl" style="margin-top:6px;">Date: ______________________</div>
            <div class="sig-lbl" style="margin-top:4px;">Signature: _________________</div>
        </td>
    </tr>
    </table>

    <table class="sig-info">
    <tr>
        <td>
            <strong>#b.name#</strong><br>
            #b.address#<br>
            #b.city_state_zip#<br>
            #b.phone#
        </td>
        <td>
            <strong>#clientName#</strong><br>
            <cfif len(trim(qAg.client_address))>#qAg.client_address#<br></cfif>
            <cfif len(trim(qAg.client_city_state_zip))>#qAg.client_city_state_zip#<br></cfif>
            #qAg.client_phone#
        </td>
    </tr>
    </table>
    </div>

    </cfoutput>
    </body></html>
    </cfdocumentsection>

    <!--- ══ PAGE 3 — MEMORANDUM OF UNDERSTANDING ══ --->
    <cfdocumentsection>
    <html><head><style>#css#</style></head><body>
    <cfoutput>

    #lhHtml#

    <div style="text-align:center;margin-bottom:14px;">
        <div style="font-size:14pt;font-weight:bold;color:#1a1a2e;">Memorandum of Understanding</div>
        <div style="font-size:9pt;color:#666666;font-style:italic;">#dba# &amp; #clientName#</div>
    </div>

    <p>#dba#, an integrated marketing communications firm, agrees to complete all marketing
    services as listed above for <strong>#clientName#</strong>.
    The services will include the following criteria:</p>

    <ul>
        <li>The contract shall be in effect <strong>Start:</strong> #contractStart#
            &nbsp;<strong>Ends:</strong> #contractEnd#.
            (Contract can be updated with a specific date if required by Client.)</li>
        <li>This contract can be canceled with 30 day written notice from either party.</li>
    </ul>

    <p><strong>Contract does not include:</strong> All Third-Party Costs (examples: website
    hosting, purchased images &mdash; $25 each, printing, media placements, travel expenses).
    These elements can be estimated upon request.</p>

    <ul>
        <li>All invoices are payable within 15 days of receipt. Invoices that become 30 days
            past due are subject to 1&frac12;% monthly service charge. Accounts that become
            60 days past due can be sent to a collection agency and the Client will assume
            responsibility for all collection of legal fees necessitated by default in
            payment.</li>
        <li>#dba# will be responsible for all payroll, income, and unemployment tax
            deductions/payments due to services incurred by Agency.</li>
        <li>Agency warrants and represents that to the best of our knowledge any artwork
            designed by our firm is original, does not contain any scandalous, libelous, or
            unlawful matter and has not been previously published. We further assert that we
            have full authority to make this agreement.</li>
        <li>The Client will make additional payments for changes requested in original
            assignment. However, no additional payment shall be made for changes required to
            conform to the original assignment description.</li>
        <li>Cancellation fees are due based on the amount of work completed. Fifty percent
            (50%) of the final fee is due within 30 days notification that for any reason the
            job is canceled or postponed before the final stage. One hundred percent (100%)
            of the total fee is due despite cancellation or postponement of the job if the
            art has been completed. Either party can terminate this contract with thirty days
            written notice and no balance owing.</li>
        <li>Both parties shall fully comply with all applicable federal, state, and local laws
            and regulations during this time. Both parties shall adhere to the PRSA Code of
            Ethics.</li>
        <li>All information about the business and this contract shall be kept confidential
            until both parties agree to the release of said information to the public
            forum.</li>
        <li>Both parties agree that the Client owns all artwork (finished project) except for
            the native mechanical files, raw video or raw photography used to create said
            artwork. Those files are retained by Agency unless and until a transfer of
            ownership agreement is made and transfer fee has been paid.</li>
        <li>The Client will indemnify the individual artist, as well as Agency, against all
            claims and expenses arising from uses for which the Client does not have the
            rights to or authority to use.</li>
        <li>Should any disagreement result over this contract, the prevailing party shall be
            entitled to receive reasonable attorney fees and costs set by the court. This
            agreement will be governed by the laws of the State of Washington. Venue
            regarding any dispute shall lie in Yakima County.</li>
    </ul>

    <p>I, <span style="border-bottom:1pt solid #444444;display:inline-block;min-width:240px;
        padding-bottom:2px;">#qAg.client_representative#</span>
    a representing member of <strong>#clientName#</strong>,
    agree that #dba# will provide the service explained in this contract according to the
    terms of this contract.</p>

    <div class="sig-wrap">
    <table class="sig">
    <tr>
        <td style="width:55%;padding-right:20px;">
            <div class="sig-line"></div>
            <div class="sig-lbl">#clientName# | Company Representative</div>
            <div class="sig-lbl" style="margin-top:8px;">Date: ______________________</div>
        </td>
        <td style="width:45%;padding-left:20px;">
            <div class="sig-line"></div>
            <div class="sig-lbl">#dba# | #b.signer_title# | #b.signer_name#</div>
            <div class="sig-lbl" style="margin-top:8px;">Date: ______________________</div>
        </td>
    </tr>
    </table>
    </div>

    </cfoutput>
    </body></html>
    </cfdocumentsection>

</cfdocument>

<!--- ── Stream PDF to browser ────────────────────────────────────────────── --->
<cfheader name="Content-Disposition" value="attachment; filename=""#pdfFilename#""">
<cfcontent type="application/pdf" variable="#pdfBytes#" reset="true">
