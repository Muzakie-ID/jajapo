<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Support\Str;

class NewComplaint extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Complaint $complaint)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'complaint';
    }

    public function title(): string
    {
        return "Komplain baru untuk {$this->complaint->order->code}";
    }

    public function body(): string
    {
        return $this->complaint->reasonLabel().', minta refund '.rupiah($this->complaint->amount_requested).'. Tanggapi dalam 24 jam.';
    }

    public function url(): string
    {
        return '/seller/komplain';
    }

    protected function mailHero(): array
    {
        $this->complaint->loadMissing('order', 'shop');

        return ['eyebrow' => "Komplain baru · {$this->complaint->shop->name}", 'title' => "{$this->complaint->order->code} dikomplain pembeli"];
    }

    protected function mailIntro(): string
    {
        return 'Tanggapi paling lambat '.tanggal($this->complaint->created_at->copy()->addDay(), withTime: true).'. Setelah itu super admin ikut memeriksa.';
    }

    protected function mailHighlight(): ?array
    {
        return [
            'label' => 'Refund yang diminta',
            'value' => rupiah($this->complaint->amount_requested),
            'note' => 'Nominal ini ditahan dari saldo tersedia sampai komplain diputuskan.',
        ];
    }

    protected function mailDetails(): array
    {
        $c = $this->complaint->loadMissing('buyer', 'order.items');

        return [
            'Pembeli' => $c->buyer->name,
            'Pesanan' => "{$c->order->code}, {$c->order->itemsLabel()}",
            'Alasan' => $c->reasonLabel(),
            'Cerita pembeli' => Str::limit($c->description, 300),
            'Foto bukti' => $c->photo_path ? 'Ada, lihat di Jajapo' : 'Tidak ada',
        ];
    }

    protected function mailAction(): string
    {
        return 'Tanggapi komplain';
    }

    protected function mailPreheader(): string
    {
        $this->complaint->loadMissing('buyer', 'order');

        return "{$this->complaint->buyer->name} minta refund ".rupiah($this->complaint->amount_requested)." untuk {$this->complaint->order->code}. {$this->complaint->reasonLabel()}.";
    }
}
