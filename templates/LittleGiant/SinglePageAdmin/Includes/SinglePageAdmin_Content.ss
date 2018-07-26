<div id="pages-controller-cms-content" class="has-panel cms-content flexbox-area-grow fill-width fill-height cms-content cms-tabset $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content" data-ignore-tab-state="true">
    $Tools

    <div class="fill-height flexbox-area-grow">
        <div class="cms-content-header north">
            <div class="cms-content-header-info flexbox-area-grow vertical-align-items fill-width">
                <% if $BreadcrumbsBackLink %><a href="$BreadcrumbsBackLink" class="btn btn-secondary btn--no-text font-icon-left-open-big hidden-lg-up toolbar__back-button"></a><% end_if %>
                <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
            </div>
        </div>

        <div class="flexbox-area-grow fill-height">
            $EditForm
        </div>
    </div>
</div>
