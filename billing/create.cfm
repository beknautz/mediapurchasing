<cfset pageTitle = "Add Bill">
<cfset billSvc  = new components.BillingService()>
<cfset crmSvc   = new components.CRMService()>
<cfset vendors  = billSvc.getVendors()>

<!--- Get media buys for linking --->
<cfset buySvc   = new components.MediaBuyService()>

<!--- Handle upload + save --->
<cfif structKeyExists(form, "vendor_id")>
    <cfset filePath = "">

    <cfif len(form.bill_file ?: "")>
        <!--- Handle file upload --->
        <cfset uploadDir = expandPath("/uploads/bills/")>
        <cfset safeName  = "#createUUID()#_#reReplace(getFileFromPath(form.bill_file), '[^a-zA-Z0-9._-]', '_', 'all')#">
        <cffile action="upload" filefield="bill_file" destination="#uploadDir#" nameconflict="makeunique" result="uploadResult">
        <cfset filePath = "/uploads/bills/#uploadResult.serverFile#">
    </cfif>

    <cfset result = billSvc.createBill({
        vendor_id      : form.vendor_id,
        media_buy_id   : val(form.media_buy_id ?: 0),
        invoice_number : form.invoice_number ?: "",
        invoice_date   : form.invoice_date   ?: "",
        due_date       : form.due_date        ?: "",
        amount         : val(form.amount      ?: 0),
        intake_method  : "manual_upload",
        priority       : form.priority        ?: "normal",
        notes          : form.notes           ?: "",
        file_path      : filePath
    })>

    <cfif result.success>
        <cfset session.flash = "Bill added to queue.">
        <cflocation url="/billing/view.cfm?id=#result.id#" addtoken="false">
    </cfif>
</cfif>

<cfinclude template="/includes/header.cfm">
<cfoutput>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/billing/index.cfm">Bill Queue</a></li>
        <li class="breadcrumb-item active">Add Bill</li>
    </ol>
</nav>

<h1 class="page-title">Add Bill to Queue</h1>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Vendor <span class="text-danger">*</span></label>
                            <select name="vendor_id" class="form-select" required>
                                <option value="">Select vendor…</option>
                                <cfloop query="vendors">
                                    <option value="#id#">#encodeForHTML(company_name)#</option>
                                </cfloop>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control"
                                   placeholder="e.g. INV-2024-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount ($) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal" selected>Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Link to Media Buy (optional)</label>
                            <select name="media_buy_id" class="form-select">
                                <option value="">None</option>
                                <cfset buys = queryExecute(
                                    "SELECT id, title FROM media_buys WHERE status NOT IN ('cancelled') ORDER BY title",
                                    {}, { datasource: application.datasource }
                                )>
                                <cfloop query="buys">
                                    <option value="#id#">#encodeForHTML(title)#</option>
                                </cfloop>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Invoice File (PDF, JPG, PNG)</label>
                            <input type="file" name="bill_file" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png,.tif,.csv,.xlsx">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="Any notes about this invoice…"></textarea>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Add to Queue
                        </button>
                        <a href="/billing/index.cfm" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
