<cfif !isDefined("url.id") OR !val(url.id)>
    <cflocation url="/billing/index.cfm" addtoken="false">
</cfif>

<cfset billSvc = new components.BillingService()>
<cfset authSvc = new components.AuthService()>
<cfset result  = billSvc.getBill(val(url.id))>

<cfif !result.found>
    <cflocation url="/billing/index.cfm" addtoken="false">
</cfif>

<cfset b = result.bill>

<!--- Handle form actions --->
<cfif structKeyExists(form, "action")>
    <cfswitch expression="#form.action#">
        <cfcase value="update_status">
            <cfset billSvc.updateBillStatus(b.id, form.new_status, form.notes ?: "")>
            <cfset session.flash = "Bill status updated.">
            <cflocation url="/billing/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>
        <cfcase value="assign">
            <cfset billSvc.assignBill(b.id, val(form.assigned_to))>
            <cfset session.flash = "Bill assigned.">
            <cflocation url="/billing/view.cfm?id=#b.id#" addtoken="false">
        </cfcase>
    </cfswitch>
</cfif>

<cfset buyers  = authSvc.getUsers()>
<cfset pageTitle = "Bill ##encodeForHTML(b.invoice_number ?: b.id)#">
<cfinclude template="/includes/header.cfm">

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/billing/index.cfm">Bill Queue</a></li>
        <li class="breadcrumb-item active">Bill ##encodeForHTML(b.invoice_number ?: b.id)#</li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-receipt me-2"></i>Invoice Details</span>
                <span class="badge
                    <cfswitch expression="#b.bill_status#">
                        <cfcase value="queued">bg-warning text-dark</cfcase>
                        <cfcase value="under_review">bg-primary</cfcase>
                        <cfcase value="approved">bg-success</cfcase>
                        <cfcase value="paid">bg-success</cfcase>
                        <cfcase value="disputed">bg-danger</cfcase>
                        <cfdefaultcase>bg-secondary</cfdefaultcase>
                    </cfswitch>">
                    #b.bill_status#
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6"><strong>Vendor:</strong> #encodeForHTML(b.vendor_name)#</div>
                    <div class="col-sm-6"><strong>Invoice ##:</strong> #encodeForHTML(b.invoice_number ?: "N/A")#</div>
                    <div class="col-sm-4"><strong>Amount:</strong> <span class="fs-4 fw-bold text-primary">$#numberFormat(b.amount,"9,999.99")#</span></div>
                    <div class="col-sm-4"><strong>Invoice Date:</strong> <cfif len(b.invoice_date)>#dateFormat(b.invoice_date,"mmm d, yyyy")#</cfif></div>
                    <div class="col-sm-4"><strong>Due Date:</strong>
                        <cfif len(b.due_date)>
                            <span class="#(now() GT b.due_date AND b.bill_status NEQ 'paid' ? 'text-danger fw-bold' : '')#">
                                #dateFormat(b.due_date,"mmm d, yyyy")#
                            </span>
                        </cfif>
                    </div>
                    <cfif len(b.buy_title)>
                        <div class="col-12">
                            <strong>Linked Campaign:</strong>
                            <a href="/media-buys/view.cfm?id=#b.media_buy_id#">#encodeForHTML(b.buy_title)#</a>
                        </div>
                    </cfif>
                    <div class="col-sm-6"><strong>Intake Method:</strong> #replace(b.intake_method,"_"," ","all")#</div>
                    <div class="col-sm-6"><strong>Priority:</strong>
                        <span class="badge
                            <cfswitch expression="#b.priority#">
                                <cfcase value="urgent">bg-danger</cfcase>
                                <cfcase value="high">bg-warning text-dark</cfcase>
                                <cfcase value="normal">bg-primary</cfcase>
                                <cfdefaultcase>bg-secondary</cfdefaultcase>
                            </cfswitch>">
                            #b.priority#
                        </span>
                    </div>
                    <cfif len(b.notes)>
                        <div class="col-12"><strong>Notes:</strong><br><span class="text-muted">#encodeForHTML(b.notes)#</span></div>
                    </cfif>
                    <cfif len(b.processing_notes)>
                        <div class="col-12"><strong>Processing Notes:</strong><br><span class="text-muted">#encodeForHTML(b.processing_notes)#</span></div>
                    </cfif>
                </div>

                <!--- File attachment --->
                <cfif len(b.file_path)>
                    <div class="mt-3">
                        <a href="#encodeForHTMLAttribute(b.file_path)#" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-paperclip me-1"></i>View Invoice File
                        </a>
                    </div>
                </cfif>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!--- Update status --->
        <div class="card mb-3">
            <div class="card-header">Update Status</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="new_status" class="form-select">
                            <cfloop list="queued,under_review,approved,paid,disputed,rejected" index="s">
                                <option value="#s#" #(b.bill_status EQ s ? 'selected' : '')#>#replace(s,"_"," ","all")#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                </form>
            </div>
        </div>

        <!--- Assign --->
        <div class="card mb-3">
            <div class="card-header">Assign To</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="assign">
                    <div class="mb-3">
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <cfloop query="buyers">
                                <option value="#id#" #(b.assigned_to EQ id ? 'selected' : '')#>#encodeForHTML(name)#</option>
                            </cfloop>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">Assign</button>
                </form>
            </div>
        </div>

        <!--- Send confirmation to vendor --->
        <div class="card">
            <div class="card-header">Vendor Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="/communications/compose.cfm?bill_id=#b.id#&to_email=#encodeForURL(b.vendor_email)#"
                   class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-envelope me-1"></i>Email Vendor
                </a>
            </div>
        </div>
    </div>
</div>

<cfinclude template="/includes/footer.cfm">
