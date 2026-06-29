<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chatbot extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'personality_prompt', 'rive_path'];

    public function knowledgeSources()
    {
        return $this->hasMany(ChatbotKnowledgeSource::class);
    }
}
