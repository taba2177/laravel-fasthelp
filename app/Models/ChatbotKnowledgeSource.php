<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotKnowledgeSource extends Model
{
    use HasFactory;

    protected $fillable = ['chatbot_id', 'source_type'];

    public function chatbot()
    {
        return $this->belongsTo(Chatbot::class);
    }
}
