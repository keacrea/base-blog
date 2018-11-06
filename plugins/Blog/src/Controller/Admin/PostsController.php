<?php
namespace Blog\Controller\Admin;

use Blog\Controller\AppController;
use Cake\Database\Type;
use Cake\Event\Event;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\Utility\Hash;

/**
 * Posts Controller
 *
 * @property \Blog\Model\Table\PostsTable $Posts
 * @property \App\Controller\Component\SaveCloseComponentComponent $SaveClose
 *
 * @method \Blog\Model\Entity\Post[] paginate($object = null, array $settings = [])
 */
class PostsController extends AppController
{
    public function initialize(){
        parent::initialize();
        $this->loadComponent('Paginator');
        $this->loadComponent('SaveClose');

        Type::build('datetime')
            ->useLocaleParser()
            ->setLocaleFormat('dd/MM/yyyy HH:mm');
    }

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->Security->setConfig('unlockedActions', ['actions','online','deleteImg']);
    }

    /**
     * @param $administrator
     * @return bool
     */
    public function isAuthorized($administrator)
    {
        if (in_array($administrator['role'], ['admin', 'superadmin'])) {
            return true;
        }
        return false;
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        if($this->request->getSession()->check('Temp')){
            $this->request->getSession()->delete('Temp');
        }

        $this->paginate = [
            'limit' =>10,
        ];

        $query = $this->Posts->find()
            ->select(['id','name','image','content','online','slug', 'published'])
            ->orderDesc('published');

        foreach($this->request->getQuery() as $k =>  $v){
            if(!empty($v) &&  $k != 'page' && $k != 'sort' && $k != 'direction'){
                $this->paginate['conditions']['Posts.'.$k.' LIKE'] =  '%'.trim($v).'%';
            }
        }

        try{
            $posts = $this->paginate($query);
        } catch(NotFoundException $e){
            $this->redirect(array('action'=>'index'));
        }

        $this->set(compact('posts'));
    }

    /**
     * @param null $type
     *
     * @return mixed
     * @internal param string $lang
     *
     */
    public function add()
    {
        $post = $this->Posts->newEntity();

        if ($this->request->is('post')) {
            $post = $this->Posts->patchEntity($post, $this->request->getData());

            if ($this->Posts->save($post)) {
                $this->Flash->success('Le contenu a bien été enregistré.');


                $this->SaveClose->redirect($post->id);
            } else {
                $this->Flash->error('Merci de vérifier les informations saisies');
            }
        }


        if (!$this->request->getSession()->check('Temp')) {
                $this->request->getSession()->write('Temp', $this->referer());
        }
        $categories = $this->Posts->Categories->listCategories();
        $this->set(compact('post', 'categories'));
    }


    /**
     * Edit method
     *
     * @param null $id
     *
     * @return \Cake\Network\Response|void
     */
    public function edit($id = null)
    {
        $post = $this->Posts->get($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $post = $this->Posts->patchEntity($post, $this->request->getData());


            if ($this->Posts->save($post)) {

                $this->Flash->success('Le contenu a bien été mis à jour.');

                $this->SaveClose->redirect($post->id);

            } else {
                $this->Flash->error('Merci de verifier les informations saisies.');
            }

        }
        if (!$this->request->getSession()->check('Temp')) {
            $this->request->getSession()->write('Temp', $this->referer());
        }
        $categories = $this->Posts->Categories->listCategories();
        $this->set(compact('post', 'categories'));

    }


    /**
     * @param null $id
     * @return \Cake\Http\Response|null
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $post = $this->Posts->get($id);
        if ($this->Posts->delete($post)) {
            $this->Flash->success('L\'article a bien été supprimé');
        } else {
            $this->Flash->error('Impossible de supprimer l\'article.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param $id
     */
    public function confirm($id)
    {
        $this->viewBuilder()->setLayout('ajax');
        $this->set('id',$id);
    }


    public function online( )
    {
        if ($this->request->is('ajax')) {
            $post = $this->Posts->find()->where(['Posts.id' => $this->request->getData('id')])->first();
//            $post->online = (!empty($this->request->getData('online'))) ? true : false;
            $post->online = (!$post->online) ? true : false;
            $this->Posts->save($post);
        }
        exit();
    }

    public function actions()
    {

        if ($this->request->is('ajax')) {

            // Force le controller à rendre une réponse JSON.
            // Et définit le type de réponse de la requete AJAX
            $this->RequestHandler->renderAs($this, 'json');
            $this->response->withType('application/json');
            $this->viewBuilder()->setLayout('ajax');

            $data = $this->request->getData();

            $statut = 0;
            if (empty($data['action']) || empty($data['datas'])) {
                $statut = 0;
            }
            $postIds = hash::extract($data['datas'], '{n}.value');

            if ($data['action'] == 'offline') {
                if ($this->Posts->query()
                    ->update()
                    ->set(['online' => false])
                    ->where(['Posts.id IN' => $postIds])
                    ->execute()
                ) {
                    $statut = 1;
                }
            }

            if ($data['action'] == 'online') {

                if ($this->Posts->query()
                    ->update()
                    ->set(['online' => true])
                    ->where(['Posts.id IN' => $postIds])
                    ->execute()
                ) {
                    $statut = 1;
                }

            }

            if ($data['action'] == 'delete') {
                foreach ($postIds as $pId) {
                    $postId = $this->Posts->find()
                        ->select(['id'])
                        ->where(['Posts.id' => $pId])
                        ->first();
                    if ($postId) {
                        $this->Posts->delete($postId);
                    }
                }
                $statut = 1;

            }
            $this->set('statut', json_encode($statut));
        } else {
            throw new MethodNotAllowedException('Cette action n\'est pas autorisée.', 403);
        }
    }


    public function confirmImg($id, $file = 'image'){

        $this->viewBuilder()->setLayout('ajax');
        if($id)
            $post = $this->Posts->find()
                ->where(['Posts.id'=>$id])
                ->select(['id',$file])
                ->first();

        $this->set(compact('id','file'));

    }


    public function deleteImg($id = null)
    {
        if($this->request->is('ajax')){
            $this->RequestHandler->renderAs($this, 'json');
            $this->response->withType('application/json');
            $this->viewBuilder()->setLayout('ajax');


            $post = $this->Posts->get($id);
            $post->image = '';
            $post->alt = '';
            if ($this->Posts->save($post)) {
                $statut = 1;
            } else {
                $statut = 0;
            }
            $this->set('statut', json_encode($statut));
        }else {
            throw new MethodNotAllowedException('Cette action n\'est pas autorisée.', 403);
        }

    }

}
