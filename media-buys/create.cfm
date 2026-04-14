<cfset authSvc = new components.AuthService()>
<cfset authSvc.requireRole("admin,buyer")>

<cfset crmSvc  = new components.CRMService()>
<cfset buySvc  = new components.MediaBuyService()>
<cfset clients = crmSvc.getClients()>
<cfset vendors = crmSvc.getVendors()>
<cfset buyers  = authSvc.getUsers(role="buyer")>

<!--- Handle form post --->
<cfif structKeyExists(form, "title")>
    <cfset itemsArray = []>
    <cfset idx = 0>
    <cfloop condition="structKeyExists(form, 'items[#idx#][description]')">
        <cfset arrayAppend(itemsArray, {
            description : form["items[#idx#][description]"] ?: "",
            placement   : form["items[#idx#][placement]"]   ?: "",
            spots       : val(form["items[#idx#][spots]"]   ?: 1),
            unit_cost   : val(form["items[#idx#][unit_cost]"] ?: 0),
            total_cost  : val(form["items[#idx#][total_cost]"] ?: 0)
        })>
        <cfset idx++>
    </cfloop>

    <cfset data = {
        title        : form.title,
        client_id    : form.client_id,
        vendor_id    : form.vendor_id,
        buyer_id     : form.buyer_id ?: session.user.id,
        media_type   : form.media_type,
        flight_start : form.flight_start ?: "",
        flight_end   : form.flight_end   ?: "",
        market       : form.market       ?: "",
        original_cost: val(form.original_cost ?: 0),
        description  : form.description  ?: "",
        internal_notes: form.internal_notes ?: "",
        items        : itemsArray
    }>

    <cfset result = buySvc.saveMediaBuy(data)>
    <cfif result.success>
        <cfset session.flash = "Media buy created successfully.">
        <cflocation url="/media-buys/view.cfm?id=#result.id#" addtoken="false">
    </cfif>
</cfif>

<cfset pageTitle = "New Media Buy">
<cfinclude template="/includes/header.cfm">

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/media-buys/index.cfm">Media Buys</a></li>
        <li class="breadcrumb-item active">New</li>
    </ol>
</nav>

<h1 class="page-title">New Media Buy</h1>

<form method="post" id="buyForm">
<div class="row g-4">

    <div class="col-lg-8">

        <!--- Basic Info --->
        <div class="card mb-4">
            <div class="card-header">Campaign Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Campaign Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               value="#encodeForHTMLAttribute(form.title ?: '')#">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client <span class="text-danger">*</span></label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Select client…</option>
                            <cfloop query="clients">
                                <option value="#id#" #(isDefined('form.client_id') AND form.client_id EQ id ? 'selected' : '')#>
                                    #encodeForHTML(company_name)#
                                </option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vendor (Media Company) <span class="text-danger">*</span></label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="">Select vendor…</option>
                            <cfloop query="vendors">
                                <option value="#id#" #(isDefined('form.vendor_id') AND form.vendor_id EQ id ? 'selected' : '')#>
                                    #encodeForHTML(company_name)#
                                </option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Media Type <span class="text-danger">*</span></label>
                        <select name="media_type" class="form-select" required>
                            <option value="">Select…</option>
                            <cfloop list="TV,Radio,Print,Digital,OOH,Streaming,Podcast,Social" index="mt">
                                <option value="#mt#" #(isDefined('form.media_type') AND form.media_type EQ mt ? 'selected' : '')#>#mt#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Flight Start</label>
                        <input type="date" name="flight_start" class="form-control"
                               value="#encodeForHTMLAttribute(form.flight_start ?: '')#">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Flight End</label>
                        <input type="date" name="flight_end" class="form-control"
                               value="#encodeForHTMLAttribute(form.flight_end ?: '')#">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Market / DMA</label>
                        <input type="text" name="market" class="form-control"
                               value="#encodeForHTMLAttribute(form.market ?: '')#" placeholder="e.g. New York DMA">
                    </div>
                    <cfif session.role EQ "admin">
                        <div class="col-md-6">
                            <label class="form-label">Assigned Buyer</label>
                            <select name="buyer_id" class="form-select">
                                <cfloop query="buyers">
                                    <option value="#id#" #(session.user.id EQ id ? 'selected' : '')#>#encodeForHTML(name)#</option>
                                </cfloop>
                            </select>
                        </div>
                    </cfif>
                    <div class="col-12">
                        <label class="form-label">Description / Brief</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Campaign details, target audience, objectives…">#encodeForHTML(form.description ?: '')#</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Internal Notes</label>
                        <textarea name="internal_notes" class="form-control" rows="2"
                                  placeholder="Internal notes (not visible to client)">#encodeForHTML(form.internal_notes ?: '')#</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!--- Line Items --->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Line Items</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLineItem()">
                    <i class="bi bi-plus-lg me-1"></i>Add Line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 line-items-table">
                        <thead>
                            <tr>
                                <th style="min-width:180px">Description</th>
                                <th style="min-width:120px">Placement</th>
                                <th style="width:80px">Spots</th>
                                <th style="width:110px">Unit Cost</th>
                                <th style="width:110px">Total</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody">
                            <tr>
                                <td><input type="text"   name="items[0][description]" class="form-control form-control-sm" placeholder="Description" required></td>
                                <td><input type="text"   name="items[0][placement]"   class="form-control form-control-sm" placeholder="e.g. AM Drive"></td>
                                <td><input type="number" name="items[0][spots]"        class="form-control form-control-sm item-spots"    value="1" min="1"></td>
                                <td><input type="number" name="items[0][unit_cost]"    class="form-control form-control-sm item-unitcost" step="0.01" value="0.00"></td>
                                <td><input type="number" name="items[0][total_cost]"   class="form-control form-control-sm item-total"    step="0.01" value="0.00" readonly></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem(this)"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Grand Total</td>
                                <td><span id="grandTotal">$0.00</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <input type="hidden" name="original_cost" id="originalCostInput" value="0">
            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="card mb-3 sticky-top" style="top:80px">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Workflow</h6>
                <p class="text-muted small mb-3">After saving, you can send a request email to the vendor and manage the full negotiation &amp; approval workflow from the buy detail page.</p>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save as Draft
                    </button>
                    <a href="/media-buys/index.cfm" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>

</div>
</form>

<cfinclude template="/includes/footer.cfm">
