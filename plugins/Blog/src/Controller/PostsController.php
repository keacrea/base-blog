<?php

namespace Blog\Controller;

use Blog\Controller\AppController;
use Cake\Chronos\Chronos;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Posts Controller
 *
 * @property \Blog\Model\Table\PostsTable $Posts
 *
 * @method \Blog\Model\Entity\Post[] paginate($object = null, array $settings = [])
 */
class PostsController extends AppController
{

    /**
     * View method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $page = TableRegistry::getTableLocator()->get('Pages')->findPageStatic($this->request->controller,$this->request->action);

        if(!$page){
            throw new NotFoundException('Cette page n\'existe pas.');
        }

        $this->paginate = ['limit' => 10];

        $query = $this->Posts->find()
            ->where([
                'Posts.online' => true,
                'Posts.published <=' => new Chronos()
            ])
            ->select(['id', 'slug', 'name', 'image', 'alt', 'content', 'published'])
            ->orderDesc('Posts.published');


        if (!$this->getRequest()->getSession()->check('Auth.User.id')) {
            $query->where(function ($q) {
                $q->eq('Posts.online', true);
                $q->lte('Posts.published', new Chronos());
                return $q;
            });
        }

        try {
            $posts = $this->paginate($query);
        } catch (NotFoundException $e) {
            $this->redirect(array('action' => 'index'));
        }


        $categories = $this->Posts->Categories->find()
            ->orderAsc('Categories.position')
            ->where(['Categories.level' => 0])
            ->all();
        $this->set(compact('posts', 'categories', 'page'));
    }

    /**
     * View method
     *
     * @param null $slug
     * @param string|null $id Post id.
     * @return \Cake\Http\Response|void
     */
    public function view($slug = null, $id = null)
    {

        if (!$slug || !$id) {
            throw new NotFoundException('Cette page n\'existe pas.');
        }

        $query = $this->Posts->find()
            ->where(
                [
                    'Posts.id' => $id,
                    'Posts.slug' => $slug,
                ]
            )
            ->contain(['Categories']);

        if (!$this->request->getSession()->check('Auth.User.id')) {
            $query->where(function ($q) {
                $q->eq('Posts.online', true);
                $q->lte('Posts.published', new Chronos());
                return $q;
            });
        }

        $post = $query->first();

        if (is_null($post)) {
            throw new NotFoundException('Cet article n\'existe pas.');
        }

        if (!$this->request->getSession()->check('Auth.User.id')) {
            $post->view_count++;
            $this->Posts->save($post);
        }

        $categories = $this->Posts->Categories->find()
            ->orderAsc('Categories.position')
            ->all()
        ;


        $this->set(compact('post', 'categories'));
    }

}
