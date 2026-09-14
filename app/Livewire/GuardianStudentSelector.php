<?php

namespace App\Livewire;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GuardianStudentSelector extends Component implements HasForms
{
    use InteractsWithForms;

    public ?int $studentId = null;

    public function mount(): void
    {
        $this->studentId = auth()->user()?->activeGuardianStudentId();
        $this->form->fill(['studentId' => $this->studentId]);
    }

    public function form(Form $form): Form
    {
        $user = auth()->user();

        $options = $user !== null
            ? $user->guardianStudents()->orderBy('name')->pluck('name', 'students.id')->all()
            : [];

        return $form
            ->schema([
                Select::make('studentId')
                    ->label('Aluno')
                    ->options($options)
                    ->placeholder(count($options) === 1 ? null : 'Selecionar aluno')
                    ->hiddenLabel()
                    ->live()
                    ->selectablePlaceholder(count($options) > 1)
                    ->disabled(count($options) <= 1)
                    ->afterStateUpdated(function (?int $state): void {
                        $this->updateActiveStudent($state);
                    })
                    ->prefixIcon('heroicon-m-users'),
            ]);
    }

    public function updateActiveStudent(?int $studentId): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->isGuardian()) {
            return;
        }

        if ($studentId === null) {
            $user->setActiveGuardianStudent(null);
            $this->redirect(request()->header('Referer') ?? url()->current(), navigate: true);

            return;
        }

        if ($user->setActiveGuardianStudent($studentId)) {
            $this->redirect(request()->header('Referer') ?? url()->current(), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.guardian-student-selector');
    }
}
