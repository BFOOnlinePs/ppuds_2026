<?php

namespace Modules\Core\Interfaces;

interface SidebarItemsInterface {
    public function toArray();
    public function canSee();
}
