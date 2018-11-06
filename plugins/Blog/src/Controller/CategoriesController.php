<?php

namespace Blog\Controller;

use Blog\Controller\AppController;
use Cake\Chronos\Chronos;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;

/**
 * Categories Controller
 *
 * @property \Blog\Model\Table\CategoriesTable $Categories
 *
 * @method \Blog\Model\Entity\Category[] paginate($object = null, array $settings = [])
 */
class CategoriesController extends AppController
{

    public $paginate = [
        'limit' => 5,
    ];

    /**
     * View method
     *
     * @param null $slug
     * @return \Cake\Http\Response|void
     */
    public function view($slug = null)
    {

        if (!$slug) {
            throw new NotFoundException('Cette page n\'existe pas.');
        }

        $query = $this->Categories->find()
            ->where(
                [
                    'Categories.slug' => $slug,
                ]
            )
            ->contain([
                'ChildCategories' => function (Query $q) {
                    return $q
                        ->where([
                            'ChildCategories.online' => true,
                        ])
                        ->orderAsc('ChildCategories.position')
                        ;
                },
            ]);

        if (!$this->request->getSession()->check('Auth.User.id')) {
            $query->where(function ($q) {
                $q->eq('Categories.online', true);
                return $q;
            });
        }

        $category = $query->first();

        if (is_null($category)) {
            throw new NotFoundException('Cette catégorie n\'existe pas.');
        }
        $categories = $this->Categories->find()
            ->where([
                'Categories.level ' => 0,
            ])
            ->all();


        $posts = $this->Categories->Posts->find()
            ->where([
                'Posts.online' => true,
                'Posts.category_id' => $category->id
            ])
            ->select(['id', 'slug', 'name', 'image', 'published'])
            ->contain(['Categories' => function (Query $q) {
                $q
                    ->select(['item']);
                return $q;
            }])
            ->orderDesc('Posts.published');

        $posts = $this->paginate($posts);


        $this->set(compact('category', 'categories', 'posts', 'parents'));
    }


}
