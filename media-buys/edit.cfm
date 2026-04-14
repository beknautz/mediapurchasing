<cfset authSvc = new components.AuthService()>
<cfset authSvc.requireRole("admin,buyer")>

<cfif !isDefined("url.id") OR !val(url.id)>
    <cflocation url="/media-buys/index.cfm" addtoken="false">
</cfif>

<cfset buySvc  = new components.MediaBuyService()>
<cfset crmSvc  = new components.CRMService()>
<cfset detail  = buySvc.getMediaBuy(val(url.id))>

<cfif !structCount(detail)>
    <cflocation url="/media-buys/index.cfm" addtoken="false">
</cfif>

<cfset b       = detail.buy>
<cfset existingItems = detail.items>
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
        id           : b.id,
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
        <cfset session.flash = "Media buy updated.">
        <cflocation url="/media-buys/view.cfm?id=#b.id#" addtoken="false">
    </cfif>
</cfif>

<cfset pageTitle = "Edit: #b.title#">
<cfinclude template="/includes/header.cfm">
<cfoutput>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/media-buys/index.cfm">Media Buys</a></li>
        <li class="breadcrumb-item"><a href="/media-buys/view.cfm?id=#b.id#">#encodeForHTML(b.title)#</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
</nav>

<h1 class="page-title">Edit Media Buy</h1>

<form method="post">
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">Campaign Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Campaign Title</label>
                        <input type="text" name="title" class="form-control"
                               value="#encodeForHTMLAttribute(b.title)#" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-select" required>
                            <cfloop query="clients">
                                <option value="#id#" #(b.client_id EQ id ? 'selected' : '')#>#encodeForHTML(company_name)#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select" required>
                            <cfloop query="vendors">
                                <option value="#id#" #(b.vendor_id EQ id ? 'selected' : '')#>#encodeForHTML(company_name)#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Media Type</label>
                        <select name="media_type" class="form-select" required>
                            <cfloop list="TV,Radio,Print,Digital,OOH,Streaming,Podcast,Social" index="mt">
                                <option value="#mt#" #(b.media_type EQ mt ? 'selected' : '')#>#mt#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Flight Start</label>
                        <input type="date" name="flight_start" class="form-control"
                               value="#len(b.flight_start) ? dateFormat(b.flight_start,'yyyy-mm-dd') : ''#">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Flight End</label>
                        <input type="date" name="flight_end" class="form-control"
                               value="#len(b.flight_end) ? dateFormat(b.flight_end,'yyyy-mm-dd') : ''#">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Market</label>
                        <input type="text" name="market" class="form-control"
                               value="#encodeForHTMLAttribute(b.market)#">
                    </div>
                    <cfif session.role EQ "admin">
                        <div class="col-md-6">
                            <label class="form-label">Assigned Buyer</label>
                            <select name="buyer_id" class="form-select">
                                <cfloop query="buyers">
                                    <option value="#id#" #(b.buyer_id EQ id ? 'selected' : '')#>#encodeForHTML(name)#</option>
                                </cfloop>
                            </select>
                        </div>
                    </cfif>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">#encodeForHTML(b.description)#</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Internal Notes</label>
                        <textarea name="internal_notes" class="form-control" rows="2">#encodeForHTML(b.internal_notes ?: '')#</textarea>
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
                                <th>Description</th><th>Placement</th><th>Spots</th>
                                <th>Unit Cost</th><th>Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody">
                            <cfloop query="existingItems">
                                <cfset i = currentRow - 1>
                                <tr>
                                    <td><input type="text"   name="items[#i#][description]" class="form-control form-control-sm" value="#encodeForHTMLAttribute(description)#"></td>
                                    <td><input type="text"   name="items[#i#][placement]"   class="form-control form-control-sm" value="#encodeForHTMLAttribute(placement)#"></td>
                                    <td><input type="number" name="items[#i#][spots]"        class="form-control form-control-sm item-spots"    value="#spots#" min="1"></td>
                                    <td><input type="number" name="items[#i#][unit_cost]"    class="form-control form-control-sm item-unitcost" step="0.01" value="#numberFormat(unit_cost,'9999.99')#"></td>
                                    <td><input type="number" name="items[#i#][total_cost]"   class="form-control form-control-sm item-total"    step="0.01" value="#numberFormat(total_cost,'9999.99')#" readonly></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </cfloop>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Grand Total</td>
                                <td><span id="grandTotal">$#numberFormat(b.original_cost,'9,999.99')#</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <input type="hidden" name="original_cost" id="originalCostInput" value="#b.original_cost#">
            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="card sticky-top" style="top:80px">
            <div class="card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                <a href="/media-buys/view.cfm?id=#b.id#" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>

</div>
</form>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
