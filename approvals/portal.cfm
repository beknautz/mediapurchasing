<!--- Public portal — NO authentication required --->
<cfif !isDefined("url.token") OR !len(url.token)>
    <cfabort>
</cfif>

<cfset approvalSvc = new components.ApprovalService()>
<cfset data        = approvalSvc.getApprovalByToken(url.token)>

<!--- Handle response submission --->
<cfset submitted   = false>
<cfset submitResult = {}>

<cfif data.found AND structKeyExists(form, "response")>
    <cfset submitResult = approvalSvc.processResponse(
        token    = url.token,
        response = form.response,
        notes    = form.revision_notes ?: "",
        ip       = cgi.remote_addr
    )>
    <cfif submitResult.success>
        <cfset submitted = true>
        <!--- Reload data to reflect new status --->
        <cfset data = approvalSvc.getApprovalByToken(url.token)>
    </cfif>
</cfif>
<cfoutput>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Buy Approval Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container">
<div class="approval-portal">

    <div class="text-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-broadcast-pin me-2 text-primary"></i>Media Buy Approval</h2>
    </div>

    <cfif !data.found>
        <div class="alert alert-danger text-center">
            <i class="bi bi-exclamation-triangle-fill fs-2 d-block mb-2"></i>
            <strong>Approval not found.</strong><br>
            This link may be invalid or has already expired.
        </div>

    <cfelseif submitted>
        <cfset r = submitResult.response>
        <cfif r EQ "approved">
            <div class="card border-success">
                <div class="card-body text-center py-5">
                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
                    <h4>Thank You — Approved!</h4>
                    <p class="text-muted">You've approved the media buy. Our team will proceed with the placement.</p>
                </div>
            </div>
        <cfelseif r EQ "revision_requested">
            <div class="card border-warning">
                <div class="card-body text-center py-5">
                    <i class="bi bi-pencil-square text-warning fs-1 d-block mb-3"></i>
                    <h4>Revision Requested</h4>
                    <p class="text-muted">We've received your feedback and will follow up shortly.</p>
                </div>
            </div>
        <cfelse>
            <div class="card border-danger">
                <div class="card-body text-center py-5">
                    <i class="bi bi-x-circle-fill text-danger fs-1 d-block mb-3"></i>
                    <h4>Buy Rejected</h4>
                    <p class="text-muted">You've rejected this media buy. Our team has been notified.</p>
                </div>
            </div>
        </cfif>

    <cfelse>
        <cfset a     = data.approval>
        <cfset items = data.items>

        <!--- Already responded --->
        <cfif a.status NEQ "pending">
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>
                This approval has already been <strong>#a.status#</strong>.
                <cfif a.status EQ "expired">The link has expired.</cfif>
            </div>
        </cfif>

        <!--- Expired check --->
        <cfif a.status EQ "pending" AND now() GT a.expires_at>
            <div class="alert alert-warning text-center">
                <i class="bi bi-clock-history me-2"></i>
                This approval link expired on #dateTimeFormat(a.expires_at,"mmm d, yyyy h:mm tt")#.
                Please contact your media buyer for a new link.
            </div>
        </cfif>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-check me-2"></i>Campaign for Approval</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="buy-detail-row"><strong>Campaign:</strong> #encodeForHTML(a.buy_title)#</div>
                        <div class="buy-detail-row"><strong>Media Type:</strong> #encodeForHTML(a.media_type)#</div>
                        <div class="buy-detail-row"><strong>Vendor:</strong> #encodeForHTML(a.vendor_name)#</div>
                    </div>
                    <div class="col-sm-6">
                        <cfif len(a.flight_start)>
                            <div class="buy-detail-row">
                                <strong>Flight Dates:</strong>
                                #dateFormat(a.flight_start,"mmmm d")# – #dateFormat(a.flight_end,"mmmm d, yyyy")#
                            </div>
                        </cfif>
                        <div class="buy-detail-row">
                            <strong>Total Cost:</strong>
                            <span class="text-primary fw-bold fs-5">
                                $#numberFormat(a.negotiated_cost ?: a.original_cost, "9,999.99")#
                            </span>
                        </div>
                    </div>
                </div>

                <cfif len(a.description)>
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>Campaign Description:</strong>
                        <p class="mb-0 text-muted mt-1">#encodeForHTML(a.description)#</p>
                    </div>
                </cfif>
            </div>
        </div>

        <!--- Line Items --->
        <cfif items.recordCount>
            <div class="card mb-4">
                <div class="card-header">Line Items Breakdown</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Description</th><th>Placement</th><th>Spots</th><th>Unit Cost</th><th>Total</th></tr></thead>
                        <tbody>
                            <cfloop query="items">
                                <tr>
                                    <td>#encodeForHTML(description)#</td>
                                    <td class="text-muted">#encodeForHTML(placement)#</td>
                                    <td>#spots#</td>
                                    <td>$#numberFormat(unit_cost,"9,999.99")#</td>
                                    <td>$#numberFormat(total_cost,"9,999.99")#</td>
                                </tr>
                            </cfloop>
                        </tbody>
                    </table>
                </div>
            </div>
        </cfif>

        <!--- Response form (only if still pending and not expired) --->
        <cfif a.status EQ "pending" AND now() LTE a.expires_at>
            <div class="card">
                <div class="card-header">Your Decision</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-4">
                            <div class="form-check form-check-lg mb-3">
                                <input class="form-check-input" type="radio" name="response"
                                       id="approved" value="approved" required>
                                <label class="form-check-label" for="approved">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Approve</strong> — I approve this media buy as presented.
                                </label>
                            </div>
                            <div class="form-check form-check-lg mb-3">
                                <input class="form-check-input" type="radio" name="response"
                                       id="revision" value="revision_requested">
                                <label class="form-check-label" for="revision">
                                    <i class="bi bi-pencil-square text-warning me-2"></i>
                                    <strong>Request Revision</strong> — I need changes before approving.
                                </label>
                            </div>
                            <div class="form-check form-check-lg">
                                <input class="form-check-input" type="radio" name="response"
                                       id="rejected" value="rejected">
                                <label class="form-check-label" for="rejected">
                                    <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                    <strong>Reject</strong> — Do not proceed with this buy.
                                </label>
                            </div>
                        </div>

                        <div id="revisionNotesGroup" style="display:none">
                            <label class="form-label fw-semibold">Please describe the revisions needed:</label>
                            <textarea name="revision_notes" class="form-control" rows="4"
                                      placeholder="What needs to change?"></textarea>
                        </div>

                        <div class="mt-4 d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send me-2"></i>Submit My Decision
                            </button>
                        </div>
                        <p class="text-muted small mt-2 text-center">
                            This approval link expires #dateTimeFormat(a.expires_at,"mmm d, yyyy h:mm tt")#.
                        </p>
                    </form>
                </div>
            </div>
        </cfif>
    </cfif>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
</cfoutput>
