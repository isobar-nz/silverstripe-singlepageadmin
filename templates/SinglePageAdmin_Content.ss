<div id="settings-controller-cms-content" class="flexbox-area-grow fill-height cms-content cms-tabset $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content" data-ignore-tab-state="true">
    <div class="cms-content-header north vertical-align-items">
        <% with $EditForm %>
            <div class="cms-content-header-info flexbox-area-grow vertical-align-items">
                <% with $Controller %>
                    <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
                <% end_with %>
            </div>
            <%--<% if $Fields.hasTabset %>--%>
            <%--<% with $Fields.fieldByName('Root') %>--%>
            <%--<div class="cms-content-header-tabs cms-tabset-nav-primary ss-ui-tabs-nav">--%>
            <%--<ul class="cms-tabset-nav-primary">--%>
            <%--<% loop $Tabs %>--%>
            <%--<li<% if $extraClass %> class="$extraClass"<% end_if %>><a href="#$id">$Title</a></li>--%>
            <%--<% end_loop %>--%>
            <%--</ul>--%>
            <%--</div>--%>
            <%--<% end_with %>--%>
            <%--<% end_if %>--%>
        <% end_with %>
    </div>
    {$EditForm}
</div>
