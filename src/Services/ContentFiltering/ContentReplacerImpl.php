<?php

namespace Fawaz\Services\ContentFiltering;

use Fawaz\App\CommentAdvanced;
use Fawaz\App\PostAdvanced;
use Fawaz\App\UserAdvanced;

class ContentReplacerImpl {
    private ContentReplacementPattern $contentReplacementPattern;
    
    public function replaceUserContent(?UserAdvanced $user): ?UserAdvanced {
        $user->setName($this->contentReplacementPattern->username($user->getName())); 
        $user->setBiography($this->contentReplacementPattern->userBiography($user->getBiography())); 
        $user->setImg($this->contentReplacementPattern->profilePicturePath($user->getImg())); 
        return $user;
    }
    public function replacePostContent(?PostAdvanced $post): ?PostAdvanced {
        $post->setTitle($this->contentReplacementPattern->postTitle($post->getTitle()));
        $post->setMediaDescription($this->contentReplacementPattern->postDescription($post->getMediaDescription()));
        $post->setMedia($this->contentReplacementPattern->postMedia($post->getMedia()));

        return $post;
    }
    public function replaceCommentContent(?CommentAdvanced $comment): ?CommentAdvanced {
        $comment->setContent($this->contentReplacementPattern->commentContent($comment->getContent())); 
        return $comment;
    }
}