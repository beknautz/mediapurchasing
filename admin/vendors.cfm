<cfset authSvc  = new components.AuthService()>
<cfset authSvc.requireRole("admin,buyer")>

<cfset crmSvc    = new components.CRMService()>
<cfset pageTitle  = "Vendors">

<!--- Save --->
<cfif structKeyExists(form, "company_name")>
    <cfset result = crmSvc.saveVendor({
        id            : val(form.id ?: 0),
        company_name  : form.company_name,
        contact_name  : form.contact_name,
        email         : form.email,
        phone         : form.phone         ?: "",
        billing_email : form.billing_email ?: "",
        media_types   : form.media_types   ?: "",
        address       : form.address       ?: "",
        notes         : form.notes         ?: ""
    })>
    <cfset session.flash = "Vendor saved.">
    <cflocation url="/admin/vendors.cfm" addtoken="false">
</cfif>

<!--- Load for edit --->
<cfset editVendor = {id:0,company_name:"",contact_name:"",email:"",phone:"",billing_email:"",media_types:"",address:"",notes:""}>
<cfif isDefined("url.edit") AND val(url.edit)>
    <cfset vd = crmSvc.getVendor(val(url.edit))>
    <cfif vd.found>
        <cfset v = vd.vendor>
        <cfset editVendor = {id:v.id,company_name:v.company_name,contact_name:v.contact_name,
            email:v.email,phone:v.phone,billing_email:v.billing_email,
            media_types:v.media_types,address:v.address,notes:v.notes}>
    </cfif>
</cfif>

<cfset vendors = crmSvc.getVendors()>
<cfinclude template="/includes/header.cfm">

<h1 class="page-title">Vendors (Media Companies)</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">All Vendors (#vendors.recordCount#)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="vendorsTable">
                    <thead><tr><th>Company</th><th>Contact</th><th>Media Types</th><th>Email</th><th></th></tr></thead>
                    <tbody>
                        <cfloop query="vendors">
                            <tr>
                                <td class="fw-semibold">#encodeForHTML(company_name)#</td>
                                <td>#encodeForHTML(contact_name)#</td>
                                <td class="small">
                                    <cfif len(media_types)>
                                        <cfloop list="#media_types#" index="mt">
                                            <span class="badge bg-light text-dark border">#trim(mt)#</span>
                                        </cfloop>
                                    </cfif>
                                </td>
                                <td class="small">#encodeForHTML(email)#</td>
                                <td class="text-end">
                                    <a href="?edit=#id#" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                        </cfloop>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">#(editVendor.id ? 'Edit Vendor' : 'New Vendor')#</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="#editVendor.id#">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" required
                               value="#encodeForHTMLAttribute(editVendor.company_name)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Name</label>
                        <input type="text" name="contact_name" class="form-control" required
                               value="#encodeForHTMLAttribute(editVendor.contact_name)#">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">Primary Email</label>
                            <input type="email" name="email" class="form-control" required
                                   value="#encodeForHTMLAttribute(editVendor.email)#">
                        </div>
                        <div class="col">
                            <label class="form-label">Billing Email</label>
                            <input type="email" name="billing_email" class="form-control"
                                   value="#encodeForHTMLAttribute(editVendor.billing_email)#"
                                   placeholder="If different from primary">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control"
                               value="#encodeForHTMLAttribute(editVendor.phone)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Media Types (comma-separated)</label>
                        <input type="text" name="media_types" class="form-control"
                               value="#encodeForHTMLAttribute(editVendor.media_types)#"
                               placeholder="TV,Radio,Digital">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">#encodeForHTML(editVendor.notes)#</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <cfif editVendor.id>
                            <a href="/admin/vendors.cfm" class="btn btn-outline-secondary">New</a>
                        </cfif>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<cfinclude template="/includes/footer.cfm">
