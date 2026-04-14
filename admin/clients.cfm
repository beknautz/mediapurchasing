<cfset authSvc  = new components.AuthService()>
<cfset authSvc.requireRole("admin,buyer")>

<cfset crmSvc   = new components.CRMService()>
<cfset pageTitle = "Clients">
<cfset search   = url.q ?: "">

<!--- Save --->
<cfif structKeyExists(form, "company_name")>
    <cfset result = crmSvc.saveClient({
        id          : val(form.id ?: 0),
        company_name: form.company_name,
        contact_name: form.contact_name,
        email       : form.email,
        phone       : form.phone    ?: "",
        address     : form.address  ?: "",
        notes       : form.notes    ?: ""
    })>
    <cfset session.flash = "Client saved.">
    <cflocation url="/admin/clients.cfm" addtoken="false">
</cfif>

<!--- Load for edit --->
<cfset editClient = {id:0,company_name:"",contact_name:"",email:"",phone:"",address:"",notes:""}>
<cfif isDefined("url.edit") AND val(url.edit)>
    <cfset cd = crmSvc.getClient(val(url.edit))>
    <cfif cd.found>
        <cfset c = cd.client>
        <cfset editClient = {id:c.id,company_name:c.company_name,contact_name:c.contact_name,
            email:c.email,phone:c.phone,address:c.address,notes:c.notes}>
    </cfif>
</cfif>

<cfset clients = crmSvc.getClients(search=search)>
<cfinclude template="/includes/header.cfm">
<cfoutput>

<h1 class="page-title">Clients</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>All Clients (#clients.recordCount#)</span>
                <input type="text" class="form-control form-control-sm w-auto" placeholder="Search…"
                       id="clientSearch" oninput="tableFilter('clientSearch','clientsTable')"
                       value="#encodeForHTMLAttribute(search)#">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="clientsTable">
                    <thead><tr><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th></th></tr></thead>
                    <tbody>
                        <cfloop query="clients">
                            <tr>
                                <td class="fw-semibold">#encodeForHTML(company_name)#</td>
                                <td>#encodeForHTML(contact_name)#</td>
                                <td class="small">#encodeForHTML(email)#</td>
                                <td class="small">#encodeForHTML(phone)#</td>
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
            <div class="card-header">#(editClient.id ? 'Edit Client' : 'New Client')#</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="#editClient.id#">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" required
                               value="#encodeForHTMLAttribute(editClient.company_name)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Name</label>
                        <input type="text" name="contact_name" class="form-control" required
                               value="#encodeForHTMLAttribute(editClient.contact_name)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="#encodeForHTMLAttribute(editClient.email)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control"
                               value="#encodeForHTMLAttribute(editClient.phone)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2">#encodeForHTML(editClient.address)#</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">#encodeForHTML(editClient.notes)#</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <cfif editClient.id>
                            <a href="/admin/clients.cfm" class="btn btn-outline-secondary">New</a>
                        </cfif>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
