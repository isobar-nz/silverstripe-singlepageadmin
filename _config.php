<?php

use LittleGiant\SinglePageAdmin\SinglePageAdmin;
use SilverStripe\Admin\CMSMenu;

CMSMenu::remove_menu_class(SinglePageAdmin::class);
