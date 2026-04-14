<cfset pageTitle = "Compose Email">
<cfset emailSvc = new components.EmailService()>
<cfset crmSvc   = new components.CRMService()>

<!--- Pre-fill from query params --->
<cfset prefillTo      = url.to_email      ?: "">
<cfset prefillBuyId   = val(url.media_buy_id ?: 0)>
<cfset prefillBillId  = val(url.bill_id ?: 0)>

<!--- Load templates for dropdown --->
<cfset templates = crmSvc.getTemplates()>

<!--- Send on POST --->
<cfset sendResult = {}>
<cfif structKeyExists(form, "to_email")>
    <cfset sendResult = emailSvc.sendAdHoc({
        to_email     : form.to_email,
        to_name      : form.to_name ?: "",
        subject      : form.subject,
        body_html    : form.body_html,
        body_text    : reReplace(form.body_html, "<[^>]*>", "", "all"),
        media_buy_id : val(form.media_buy_id ?: 0)
    })>
    <cfif sendResult.success>
        <cfset session.flash = "Email sent successfully.">
        <cfif prefillBuyId>
            <cflocation url="/media-buys/view.cfm?id=#prefillBuyId#" addtoken="false">
        <cfelse>
            <cflocation url="/communications/index.cfm" addtoken="false">
        </cfif>
    </cfif>
</cfif>

<cfinclude template="/includes/header.cfm">

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/communications/index.cfm">Communications</a></li>
        <li class="breadcrumb-item active">Compose</li>
    </ol>
</nav>

<h1 class="page-title">Compose Email</h1>

<cfif structKeyExists(sendResult,"success") AND !sendResult.success>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Send failed: #encodeForHTML(sendResult.message ?: "Unknown error")#
    </div>
</cfif>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="media_buy_id" value="#prefillBuyId#">
                    <input type="hidden" name="bill_id"      value="#prefillBillId#">

                    <div class="mb-3">
                        <label class="form-label">Load Template</label>
                        <select class="form-select" id="templateSelect" onchange="loadTemplate(this)">
                            <option value="">— Select a template (optional) —</option>
                            <cfloop query="templates">
                                <cfif is_active AND channel NEQ 'sms'>
                                    <option value="#id#" data-subject="#encodeForHTMLAttribute(subject)#"
                                            data-body="#encodeForHTMLAttribute(body_html)#">
                                        #encodeForHTML(name)# (#replace(category,"_"," ","all")#)
                                    </option>
                                </cfif>
                            </cfloop>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">To Email <span class="text-danger">*</span></label>
                            <input type="email" name="to_email" class="form-control" required
                                   value="#encodeForHTMLAttribute(prefillTo)#">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Name</label>
                            <input type="text" name="to_name" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" id="emailSubject" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Body</label>
                        <textarea name="body_html" id="emailBody" class="form-control" rows="12"
                                  placeholder="Email body (HTML supported)…"></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Send Email
                        </button>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadTemplate(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('emailSubject').value = opt.dataset.subject || '';
    document.getElementById('emailBody').value    = opt.dataset.body    || '';
}
</script>

<cfinclude template="/includes/footer.cfm">
