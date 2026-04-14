<!--- HTMX partial — communication log for a media buy --->
<cfset emailSvc  = new components.EmailService()>
<cfset buyId     = val(url.media_buy_id ?: 0)>
<cfset history   = emailSvc.getHistory(mediaBuyId=buyId)>

<cfif history.recordCount>
    <cfloop query="history">
        <div class="comm-item #encodeForHTML(direction)#">
            <div class="comm-meta">
                <span class="badge #(type EQ 'email' ? 'bg-primary' : 'bg-success')# me-1">#type#</span>
                <span class="badge #(direction EQ 'inbound' ? 'bg-info text-dark' : 'bg-secondary')#">#direction#</span>
                <span class="ms-2">#dateTimeFormat(created_at, "mmm d, yyyy h:mm tt")#</span>
                <span class="ms-2 text-muted">#encodeForHTML(from_address)# &rarr; #encodeForHTML(to_address)#</span>
            </div>
            <cfif len(subject)>
                <div class="fw-semibold small mt-1">#encodeForHTML(subject)#</div>
            </cfif>
            <cfif len(body_text)>
                <div class="small text-muted mt-1" style="white-space:pre-wrap;max-height:80px;overflow:hidden">
                    #encodeForHTML(left(body_text, 300))##(len(body_text) GT 300 ? '…' : '')#
                </div>
            </cfif>
        </div>
    </cfloop>
<cfelse>
    <p class="text-muted small">No communications logged for this buy.</p>
</cfif>
