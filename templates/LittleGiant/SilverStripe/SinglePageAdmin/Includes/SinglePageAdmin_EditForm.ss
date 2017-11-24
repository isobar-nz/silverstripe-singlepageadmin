<% if $IncludeFormTag %>
    <form $FormAttributes data-layout-type="border">
<% end_if %>
<% with $Controller %>
    $EditFormTools
<% end_with %>
    <div class="panel panel--padded panel--scrollable flexbox-area-grow <% if not $Fields.hasTabset %>cms-panel-padded<% end_if %>">
        <% if $Message %>
            <p id="{$FormName}_error" class="message $MessageType">$Message</p>
        <% else %>
            <p id="{$FormName}_error" class="message $MessageType" style="display: none"></p>
        <% end_if %>
        <fieldset>
            <% if $Legend %>
                <legend>$Legend</legend><% end_if %>
            <% loop $Fields %>
                $FieldHolder
            <% end_loop %>
            <div class="clear"><!-- --></div>
        </fieldset>
    </div>
    <div class="toolbar--south cms-content-actions cms-content-controls south">
        <% if $Actions %>
            <div class="btn-toolbar">
                <% loop $Actions %>
                    $FieldHolder
                <% end_loop %>
            </div>
        <% end_if %>
    </div>
<% if $IncludeFormTag %>
    </form>
<% end_if %>
