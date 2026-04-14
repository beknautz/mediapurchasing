<cfoutput>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/dashboard.cfm">
            <i class="bi bi-broadcast-pin me-2"></i>MediaBuy Pro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="##mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link <cfif cgi.script_name contains 'dashboard'>active</cfif>"
                       href="/dashboard.cfm">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <cfif cgi.script_name contains '/media-buys/'>active</cfif>"
                       href="/media-buys/index.cfm">
                        <i class="bi bi-cart3 me-1"></i>Media Buys
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <cfif cgi.script_name contains '/approvals/'>active</cfif>"
                       href="/approvals/index.cfm">
                        <i class="bi bi-check2-square me-1"></i>Approvals
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <cfif cgi.script_name contains '/billing/'>active</cfif>"
                       href="/billing/index.cfm">
                        <i class="bi bi-receipt me-1"></i>Billing
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <cfif cgi.script_name contains '/communications/'>active</cfif>"
                       href="/communications/index.cfm">
                        <i class="bi bi-envelope me-1"></i>Communications
                    </a>
                </li>

                <!--- Admin-only nav items --->
                <cfif session.role EQ "admin">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <cfif cgi.script_name contains '/admin/'>active</cfif>"
                           href="##" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="/admin/users.cfm"><i class="bi bi-people me-2"></i>Users</a></li>
                            <li><a class="dropdown-item" href="/admin/clients.cfm"><i class="bi bi-building me-2"></i>Clients</a></li>
                            <li><a class="dropdown-item" href="/admin/vendors.cfm"><i class="bi bi-broadcast me-2"></i>Vendors</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/admin/templates.cfm"><i class="bi bi-file-earmark-text me-2"></i>Email Templates</a></li>
                            <li><a class="dropdown-item" href="/admin/settings.cfm"><i class="bi bi-sliders me-2"></i>Workflow Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/admin/audit_log.cfm"><i class="bi bi-journal-text me-2"></i>Audit Log</a></li>
                        </ul>
                    </li>
                </cfif>

                <!--- Buyer & admin see clients/vendors shortcuts --->
                <cfif listFindNoCase("admin,buyer", session.role)>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="##" data-bs-toggle="dropdown">
                            <i class="bi bi-people me-1"></i>CRM
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="/admin/clients.cfm"><i class="bi bi-building me-2"></i>Clients</a></li>
                            <li><a class="dropdown-item" href="/admin/vendors.cfm"><i class="bi bi-broadcast me-2"></i>Vendors</a></li>
                        </ul>
                    </li>
                </cfif>

            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="##" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        #encodeForHTML(session.user.name ?: "User")#
                        <span class="badge bg-secondary ms-1">#session.role#</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                        <li><a class="dropdown-item" href="/auth/logout.cfm"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
</cfoutput>
