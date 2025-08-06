<?php

namespace App\Livewire;

use Livewire\Component;

class RoleSwitcher extends Component
{
    public function switchToStudent()
    {
        if (!auth()->user()->canSwitchRoles()) {
            return;
        }
        
        session(['acting_as' => 'student']);
        
        return $this->redirect('/dashboard', navigate: true);
    }
    
    public function switchToAdmin()
    {
        if (!auth()->user()->canSwitchRoles()) {
            return;
        }
        
        session()->forget('acting_as');
        
        return $this->redirect('/admin', navigate: true);
    }
    
    public function exitStudentView()
    {
        session()->forget('acting_as');
        
        return $this->redirect('/admin', navigate: true);
    }
    
    public function render()
    {
        return view('livewire.role-switcher');
    }
}