<cfset pageTitle  = "Media Buys">
<cfset buySvc    = new components.MediaBuyService()>
<cfset statusFilter = url.status ?: "">
<cfset page      = val(url.page ?: 1)>
<cfset buyerFilter  = (session.role EQ "buyer") ? session.user.id : 0>
<cfset result    = buySvc.getMediaBuys(status=statusFilter, buyerId=buyerFilter, page=page)>

<cfinclude template="/includes/header.cfm">
<cfoutput>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title mb-0">Media Buys</h1>
    <cfif listFindNoCase("admin,buyer", session.role)>
        <a href="/media-buys/create.cfm" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Media Buy
        </a>
    </cfif>
</div>

<!--- Status filter tabs --->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link #(!len(statusFilter)?'active':'')#" href="?">All</a></li>
    <cfloop list="draft,sent_to_vendor,pending_client_approval,negotiating,client_approved,finalized,cancelled" index="s">
        <li class="nav-item">
            <a class="nav-link #(statusFilter EQ s ? 'active' : '')#" href="?status=#s#">
                #replace(s,"_"," ","all")#
            </a>
        </li>
    </cfloop>
</ul>

<!--- Search --->
<div class="mb-3">
    <input type="text" class="form-control w-auto d-inline-block" id="buySearch"
           placeholder="Search…" oninput="tableFilter('buySearch','buysTable')">
</div>

<div class="card">
    <div class="card-body p-0">
        <cfif result.data.recordCount>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="buysTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Campaign</th>
                            <th>Client</th>
                            <th>Vendor</th>
                            <th>Media</th>
                            <th>Flight</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Buyer</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <cfloop query="result.data">
                            <tr onclick="location='/media-buys/view.cfm?id=#id#'">
                                <td class="text-muted small">#id#</td>
                                <td><strong>#encodeForHTML(title)#</strong></td>
                                <td>#encodeForHTML(client_name)#</td>
                                <td>#encodeForHTML(vendor_name)#</td>
                                <td><span class="badge bg-light text-dark border">#encodeForHTML(media_type)#</span></td>
                                <td class="small text-muted">
                                    <cfif len(flight_start)>#dateFormat(flight_start,"mmm d")# – #dateFormat(flight_end,"mmm d, yyyy")#</cfif>
                                </td>
                                <td>$#numberFormat(original_cost,"9,999")#</td>
                                <td>
                                    <span class="badge status-badge status-#lCase(status)#">
                                        #replace(status,"_"," ","all")#
                                    </span>
                                </td>
                                <td class="small">#encodeForHTML(buyer_name)#</td>
                                <td class="small text-muted">#dateFormat(updated_at,"mmm d")#</td>
                                <td onclick="event.stopPropagation()">
                                    <a href="/media-buys/view.cfm?id=#id#" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        </cfloop>
                    </tbody>
                </table>
            </div>

            <!--- Pagination --->
            <cfif result.pages GT 1>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <small class="text-muted">Showing page #result.page# of #result.pages# (#result.total# records)</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <cfloop from="1" to="#result.pages#" index="pg">
                                <li class="page-item #(pg EQ result.page ? 'active' : '')#">
                                    <a class="page-link" href="?page=#pg#&status=#statusFilter#">#pg#</a>
                                </li>
                            </cfloop>
                        </ul>
                    </nav>
                </div>
            </cfif>
        <cfelse>
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart3 fs-2 d-block mb-2"></i>No media buys found.
            </div>
        </cfif>
    </div>
</div>

</cfoutput>
<cfinclude template="/includes/footer.cfm">
