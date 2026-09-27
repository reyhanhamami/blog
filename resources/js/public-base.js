document.addEventListener('livewire:navigate', () => document.querySelector('#nav-progress')?.classList.remove('hidden'));
document.addEventListener('livewire:navigated', () => document.querySelector('#nav-progress')?.classList.add('hidden'));
