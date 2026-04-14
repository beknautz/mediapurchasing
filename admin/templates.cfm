<cfset authSvc = new components.AuthService()>
<cfset authSvc.requireRole("admin")>

<cfset crmSvc   = new components.CRMService()>
<cfset pageTitle = "Email Templates">

<!--- Handle save --->
<cfif structKeyExists(form, "name")>
    <cfset result = crmSvc.saveTemplate({
        id        : val(form.id ?: 0),
        name      : form.name,
        slug      : len(form.slug ?: "") ? form.slug : lCase(replace(form.name," ","_","all")),
        category  : form.category,
        channel   : form.channel    ?: "email",
        subject   : form.subject    ?: "",
        body_html : form.body_html  ?: "",
        body_text : form.body_text  ?: "",
        sms_body  : form.sms_body   ?: "",
        variables : form.variables  ?: "",
        is_active : form.is_active  ?: 1
    })>
    <cfset session.flash = "Template saved.">
    <cflocation url="/admin/templates.cfm" addtoken="false">
</cfif>

<!--- Delete --->
<cfif isDefined("url.delete") AND val(url.delete)>
    <cfset authSvc.requireRole("admin")>
    <cfset queryExecute(
        "DELETE FROM email_templates WHERE id=:id",
        { id: { value: val(url.delete), cfsqltype: "cf_sql_integer" } },
        { datasource: application.datasource }
    )>
    <cfset session.flash = "Template deleted.">
    <cflocation url="/admin/templates.cfm" addtoken="false">
</cfif>

<!--- Load for edit --->
<cfset editTemplate = {id:0,name:"",slug:"",category:"general",channel:"email",subject:"",body_html:"",body_text:"",sms_body:"",variables:"",is_active:1}>
<cfif isDefined("url.edit") AND val(url.edit)>
    <cfset tq = crmSvc.getTemplate(val(url.edit))>
    <cfif tq.recordCount>
        <cfset editTemplate = {
            id:tq.id, name:tq.name, slug:tq.slug, category:tq.category,
            channel:tq.channel, subject:tq.subject, body_html:tq.body_html,
            body_text:tq.body_text, sms_body:tq.sms_body, variables:tq.variables,
            is_active:tq.is_active
        }>
    </cfif>
</cfif>

<cfset templates = crmSvc.getTemplates()>

<cfinclude template="/includes/header.cfm">
<cfoutput>

<h1 class="page-title">Email / SMS Templates</h1>

<div class="row g-4">

    <!--- Template list --->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">All Templates</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Category</th><th>Channel</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                        <cfloop query="templates">
                            <tr>
                                <td class="small fw-semibold">#encodeForHTML(name)#</td>
                                <td class="small text-muted">#replace(category,"_"," ","all")#</td>
                                <td>
                                    <span class="badge #(channel EQ 'email' ? 'bg-primary' : (channel EQ 'sms' ? 'bg-success' : 'bg-info text-dark'))#">
                                        #channel#
                                    </span>
                                </td>
                                <td>
                                    <span class="badge #(is_active ? 'bg-success' : 'bg-secondary')#">
                                        #(is_active ? 'Yes' : 'No')#
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="?edit=#id#" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="?delete=#id#" class="btn btn-sm btn-outline-danger"
                                       data-confirm="Delete this template?"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        </cfloop>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!--- Edit / Create form --->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">#(editTemplate.id ? 'Edit Template' : 'New Template')#</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="#editTemplate.id#">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required
                                   value="#encodeForHTMLAttribute(editTemplate.name)#">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug (unique identifier)</label>
                            <input type="text" name="slug" class="form-control"
                                   value="#encodeForHTMLAttribute(editTemplate.slug)#"
                                   placeholder="auto-generated if blank">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <cfloop list="media_buy_request,negotiation,client_approval,client_revision,bill_received,bill_reminder,general" index="cat">
                                    <option value="#cat#" #(editTemplate.category EQ cat ? 'selected' : '')#>
                                        #replace(cat,"_"," ","all")#
                                    </option>
                                </cfloop>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Channel</label>
                            <select name="channel" class="form-select">
                                <cfloop list="email,sms,both" index="ch">
                                    <option value="#ch#" #(editTemplate.channel EQ ch ? 'selected' : '')#>#ch#</option>
                                </cfloop>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Active</label>
                            <select name="is_active" class="form-select">
                                <option value="1" #(editTemplate.is_active ? 'selected' : '')#>Yes</option>
                                <option value="0" #(!editTemplate.is_active ? 'selected' : '')#>No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Subject</label>
                            <input type="text" name="subject" class="form-control"
                                   value="#encodeForHTMLAttribute(editTemplate.subject)#"
                                   placeholder="Use {{variable}} for dynamic values">
                        </div>
                        <div class="col-12">
                            <label class="form-label">HTML Body
                                <small class="text-muted ms-2">Use {{variable_name}} for merge fields</small>
                            </label>
                            <textarea name="body_html" class="form-control" rows="8"
                                      placeholder="<p>Dear {{client_contact}},</p>…">#encodeForHTML(editTemplate.body_html)#</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Plain Text Body</label>
                            <textarea name="body_text" class="form-control" rows="4">#encodeForHTML(editTemplate.body_text)#</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SMS Body <small class="text-muted">(160 chars max)</small></label>
                            <textarea name="sms_body" class="form-control" rows="2" maxlength="160">#encodeForHTML(editTemplate.sms_body)#</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Available Variables
                                <small class="text-muted ms-2">Comma-separated list for documentation</small>
                            </label>
                            <input type="text" name="variables" class="form-control"
                                   value="#encodeForHTMLAttribute(editTemplate.variables)#"
                                   placeholder="client_name,buy_title,approval_link">
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Template</button>
                        <cfif editTemplate.id>
                            <a href="/admin/templates.cfm" class="btn btn-outline-secondary">New Template</a>
                        </cfif>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
