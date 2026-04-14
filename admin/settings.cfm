<cfset authSvc = new components.AuthService()>
<cfset authSvc.requireRole("admin")>

<cfset crmSvc   = new components.CRMService()>
<cfset pageTitle = "Workflow Settings">

<!--- Handle save --->
<cfif structKeyExists(form, "save_settings")>
    <cfset savedCount = 0>
    <cfloop list="#structKeyList(form)#" index="k">
        <cfif left(k,8) EQ "setting_">
            <cfset settingKey = mid(k, 9, len(k))>
            <cfset crmSvc.saveSetting(settingKey, form[k])>
            <cfset savedCount++>
        </cfif>
    </cfloop>
    <cfset session.flash = "#savedCount# settings saved.">
    <cflocation url="/admin/settings.cfm" addtoken="false">
</cfif>

<cfset allSettings = crmSvc.getWorkflowSettings()>

<!--- Group by setting_group --->
<cfset groups = {}>
<cfloop query="allSettings">
    <cfif !structKeyExists(groups, setting_group)>
        <cfset groups[setting_group] = []>
    </cfif>
    <cfset arrayAppend(groups[setting_group], {
        id         : id,
        key        : setting_key,
        value      : setting_value,
        label      : label,
        description: description,
        group      : setting_group
    })>
</cfloop>

<cfinclude template="/includes/header.cfm">
<cfoutput>

<h1 class="page-title">Workflow Settings</h1>

<form method="post">
    <input type="hidden" name="save_settings" value="1">

    <div class="accordion" id="settingsAccordion">
        <cfset gi = 0>
        <cfloop collection="#groups#" item="grp">
            <cfset gi++>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button #(gi GT 1 ? 'collapsed' : '')#" type="button"
                            data-bs-toggle="collapse" data-bs-target="##group_#gi#">
                        <i class="bi bi-sliders me-2"></i>#uCase(grp)# Settings
                    </button>
                </h2>
                <div id="group_#gi#" class="accordion-collapse collapse #(gi EQ 1 ? 'show' : '')#">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <cfloop array="#groups[grp]#" index="s">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">#encodeForHTML(s.label)#</label>
                                    <!--- Mask secret fields --->
                                    <cfif findNoCase("token", s.key) OR findNoCase("secret", s.key) OR findNoCase("api_key", s.key)>
                                        <input type="password" name="setting_#s.key#" class="form-control"
                                               value="#encodeForHTMLAttribute(s.value)#"
                                               autocomplete="new-password">
                                    <cfelse>
                                        <input type="text" name="setting_#s.key#" class="form-control"
                                               value="#encodeForHTMLAttribute(s.value)#">
                                    </cfif>
                                    <cfif len(s.description)>
                                        <div class="form-text">#encodeForHTML(s.description)#</div>
                                    </cfif>
                                </div>
                            </cfloop>
                        </div>
                    </div>
                </div>
            </div>
        </cfloop>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Save All Settings
        </button>
    </div>
</form>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
