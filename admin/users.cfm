<cfset authSvc  = new components.AuthService()>
<cfset authSvc.requireRole("admin")>

<cfset pageTitle = "User Management">

<!--- Save --->
<cfif structKeyExists(form, "email")>
    <cfset result = authSvc.saveUser({
        id       : val(form.id ?: 0),
        name     : form.name,
        email    : form.email,
        role     : form.role,
        phone    : form.phone     ?: "",
        password : form.password  ?: "",
        is_active: form.is_active ?: 1
    })>
    <cfif result.success>
        <cfset session.flash = "User saved.">
        <cflocation url="/admin/users.cfm" addtoken="false">
    <cfelse>
        <cfset errorMsg = result.message>
    </cfif>
</cfif>

<!--- Load for edit --->
<cfset editUser = {id:0,name:"",email:"",role:"buyer",phone:"",is_active:1}>
<cfif isDefined("url.edit") AND val(url.edit)>
    <cfset uq = queryExecute(
        "SELECT * FROM users WHERE id=:id",
        { id: { value: val(url.edit), cfsqltype: "cf_sql_integer" } },
        { datasource: application.datasource }
    )>
    <cfif uq.recordCount>
        <cfset editUser = {id:uq.id,name:uq.name,email:uq.email,role:uq.role,phone:uq.phone,is_active:uq.is_active}>
    </cfif>
</cfif>

<cfset users = authSvc.getUsers()>
<cfinclude template="/includes/header.cfm">
<cfoutput>

<h1 class="page-title">User Management</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">All Users (#users.recordCount#)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Last Login</th><th></th></tr></thead>
                    <tbody>
                        <cfloop query="users">
                            <tr>
                                <td class="fw-semibold">#encodeForHTML(name)#</td>
                                <td class="small">#encodeForHTML(email)#</td>
                                <td>
                                    <span class="badge
                                        <cfswitch expression="#role#">
                                            <cfcase value="admin">bg-danger</cfcase>
                                            <cfcase value="buyer">bg-primary</cfcase>
                                            <cfcase value="client">bg-success</cfcase>
                                            <cfdefaultcase>bg-secondary</cfdefaultcase>
                                        </cfswitch>">
                                        #role#
                                    </span>
                                </td>
                                <td>
                                    <span class="badge #(is_active ? 'bg-success' : 'bg-secondary')#">
                                        #(is_active ? 'Active' : 'Inactive')#
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <cfif len(last_login)>#dateTimeFormat(last_login,"mmm d, h:tt tt")#</cfif>
                                </td>
                                <td><a href="?edit=#id#" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        </cfloop>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">#(editUser.id ? 'Edit User' : 'New User')#</div>
            <div class="card-body">
                <cfif isDefined("errorMsg")>
                    <div class="alert alert-danger">#encodeForHTML(errorMsg)#</div>
                </cfif>
                <form method="post">
                    <input type="hidden" name="id" value="#editUser.id#">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required
                               value="#encodeForHTMLAttribute(editUser.name)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="#encodeForHTMLAttribute(editUser.email)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <cfloop list="admin,buyer,client,vendor" index="r">
                                <option value="#r#" #(editUser.role EQ r ? 'selected' : '')#>#r#</option>
                            </cfloop>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control"
                               value="#encodeForHTMLAttribute(editUser.phone)#">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password #(editUser.id ? '(leave blank to keep current)' : '')#</label>
                        <input type="password" name="password" class="form-control"
                               autocomplete="new-password" #(!editUser.id ? 'required' : '')#>
                    </div>
                    <cfif editUser.id>
                        <div class="mb-3">
                            <label class="form-label">Active</label>
                            <select name="is_active" class="form-select">
                                <option value="1" #(editUser.is_active ? 'selected' : '')#>Active</option>
                                <option value="0" #(!editUser.is_active ? 'selected' : '')#>Inactive</option>
                            </select>
                        </div>
                    </cfif>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save User</button>
                        <cfif editUser.id>
                            <a href="/admin/users.cfm" class="btn btn-outline-secondary">New</a>
                        </cfif>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
