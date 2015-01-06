<?php
/**
 * @link https://github.com/wbraganca/yii2-nested-set-behavior
 * @copyright Copyright (c) 2014 Wanderson Bragança
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace wbraganca\behaviors;

use Yii;
use yii\base\Behavior;
use yii\helpers\Html;
use kartik\icons\Icon;
use yii\helpers\Url;

/**
 * @author Wanderson Bragança <wanderson.wbc@gmail.com>
 */
class NestedSetQueryBehavior extends Behavior
{
    /**
     * @var ActiveQuery the owner of this behavior.
     */
    public $owner;

    /**
     * Gets root node(s).
     * @return ActiveRecord the owner.
     */
    public function roots()
    {
        /** @var $modelClass ActiveRecord */
        $modelClass = $this->owner->modelClass;
        $model = new $modelClass;
        $this->owner->andWhere($modelClass::getDb()->quoteColumnName($model->leftAttribute) . '=1');
        unset($model);
        return $this->owner;
    }

    public function options($root = 0, $level = null)
    {
        $res = [];
        if (is_object($root)) {
            $res[$root->{$root->idAttribute}] = str_repeat('—', $root->{$root->levelAttribute} - 1) 
                . ((($root->{$root->levelAttribute}) > 1) ? '›': '')
                . $root->{$root->titleAttribute};

            if ($level) {
                foreach ($root->children()->all() as $childRoot) {
                    $res += $this->options($childRoot, $level - 1);
                }
            } elseif (is_null($level)) {
                foreach ($root->children()->all() as $childRoot) {
                    $res += $this->options($childRoot, null);
                }
            }
        } elseif (is_scalar($root)) {
            if ($root == 0) {
                foreach ($this->roots()->all() as $rootItem) {
                    if ($level) {
                        $res += $this->options($rootItem, $level - 1);
                    } elseif (is_null($level)) {
                        $res += $this->options($rootItem, null);
                    }
                }
            } else {
                $modelClass = $this->owner->modelClass;
                $model = new $modelClass;
                $root = $modelClass::find()->andWhere([$model->idAttribute => $root])->one();
                if ($root) {
                    $res += $this->options($root, $level);
                }
                unset($model);
            }
        }
        return $res;
    }

    public function sortableTree($root = 1, $level = null)
    {
        $modelClass = $this->owner->modelClass;
        $model = new $modelClass;

        $terms = $model::find()->where("id <> 1")->addOrderBy('lft')->all();

        $newLine = "\n";

        $res = Html::beginTag('div', ['class' => 'dd', 'id' => 'sortable']) . $newLine;

        foreach ($terms as $n => $term)
        {
            if ($term->level == $level) {
                $res .= Html::endTag('li') . $newLine;
            } elseif ($term->level > $level) {
                $res .= Html::beginTag('ol', ['class' => 'dd-list', 'data-level' => $term->level - 1]) . $newLine;
            } else {
                $res .= Html::endTag('li') . $newLine;

                for ($i = $level - $term->level; $i; $i--) {
                    $res .= Html::endTag('ol') . $newLine;
                    $res .= Html::endTag('li') . $newLine;
                }
            }

            $res .= Html::beginTag('li', ['class' => 'dd-item', 'data-term' => $term->id]) . $newLine;

            //$res .= Html::beginTag('div', ['class' => (($n%2==0) ? 'odd' : 'even') /*, 'style' => 'padding-left: ' . (30 * ($model->level - 1)) . 'px'*/]);

            $res .= Html::beginTag('div', ['class' => 'dd-handle']);
            $res .= Icon::show('arrows', ['class' => 'fa-fw']);
            $res .= Html::endTag('div') . $newLine;

            $res .= Html::beginTag('div', ['class' => 'dd-content' . (($n%2==0) ? ' odd' : ' even')]);
            $res .= Html::encode($term->name);

            $res .= Html::beginTag('span', ['class' => 'action-buttons']);
            $res .= Html::a(Html::tag('span', '', ['class' => 'glyphicon glyphicon-pencil']), Url::toRoute(['update', 'id' => $term->id]), [
                'data-toggle' => 'tooltip',
                'title' => Yii::t('ecommerce', 'Update'),
                'data-pjax' => 0
            ]);
            $res .= Html::a(Html::tag('span', '', ['class' => 'glyphicon glyphicon-trash']), Url::toRoute(['delete', 'id' => $term->id]), [
                'data-toggle' => 'tooltip',
                'title' => Yii::t('ecommerce', 'Delete'),
                'data-id' => "delete-{$term->id}",
                'data-pjax' => 0,
                'data-method' => 'post',
                'data-confirm' => Yii::t('ecommerce', 'Are you sure you want to delete this item?'),
            ]);
            $res .= Html::a(Html::tag('span', '', ['class' => 'glyphicon glyphicon-eye-' . (($term->active == 1) ? 'open' : 'close')]), '#', [
                'data-toggle' => 'tooltip',
                'title' => Yii::t('ecommerce', 'Toggle active'),
                'data-pjax' => 0,
                'data-toggle-active-term' => $term->id,
            ]);

            $res .= Html::endTag('span');

            $children = $term->descendants()->count();
            if ($children > 0) {
                $res .= Html::tag('span', " ({$children})", ['class' => 'children']) ;
            }

            $res .= Html::endTag('div') . $newLine;

            //$res .= Html::endTag('div') . $newLine;

            $level = $term->level;
        }

        for ($i = $level; $i; $i--) {
            $res .= Html::endTag('li') . $newLine;
            $res .= Html::endTag('ol') . $newLine;
        }

        $res .= Html::endTag('div');

        return $res;

    }

    public function dropDownList($root = 0)
    {
        $modelClass = $this->owner->modelClass;
        $model = new $modelClass;

        $root = $model::findOne($root);
        $terms = $model::find()->where("root = {$root->id} || root = {$root->root}")->addOrderBy('lft')->all();

        $result = [];

        foreach ($terms as $n => $term) {

            $arrow = '';

            if ($term->level > 0) {

                $arrow = str_repeat("—", $term->{$root->levelAttribute});
                $arrow .= "> ";
            }

            $result[$term->id] = $arrow . $term->name;
        }

        return $result;

    }

    public function dataFancytree($root = 0, $level = null)
    {
        $data = array_values($this->prepareData2Fancytree($root, $level));
        return $this->makeData2Fancytree($data);
    }

    private function prepareData2Fancytree($root = 0, $level = null)
    {
        $res = [];
        if (is_object($root)) {
            $res[$root->{$root->idAttribute}] = [
                'key' => $root->{$root->idAttribute},
                'name' => $root->{$root->titleAttribute}
            ];

            if ($level) {
                foreach ($root->children()->all() as $childRoot) {
                    $aux = $this->prepareData2Fancytree($childRoot, $level - 1);

                    if (isset($res[$root->{$root->idAttribute}]['children']) && !empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] += $aux;
                        
                    } elseif(!empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] = $aux;
                    }
                }
            } elseif (is_null($level)) {
                foreach ($root->children()->all() as $childRoot) {
                    $aux = $this->prepareData2Fancytree($childRoot, null);
                    if (isset($res[$root->{$root->idAttribute}]['children']) && !empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] += $aux;
                        
                    } elseif(!empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] = $aux;
                    }
                }
            }
        } elseif (is_scalar($root)) {
            if ($root == 0) {
                foreach ($this->roots()->all() as $rootItem) {
                    if ($level) {
                        $res += $this->prepareData2Fancytree($rootItem, $level - 1);
                    } elseif (is_null($level)) {
                        $res += $this->prepareData2Fancytree($rootItem, null);
                    }
                }
            } else {
                $modelClass = $this->owner->modelClass;
                $model = new $modelClass;
                $root = $modelClass::find()->andWhere([$model->idAttribute => $root])->one();
                if ($root) {
                    $res += $this->prepareData2Fancytree($root, $level);
                }
                unset($model);
            }
        }
        return $res;
    }

    private function makeData2Fancytree(&$data)
    {
        $tree = [];
        foreach ($data as $key => &$item) {
            if (isset($item['children'])) {
                $item['children'] = array_values($item['children']);
                $tree[$key] = $this->makeData2Fancytree($item['children']);
            }
            $tree[$key] = $item;
        }
        return $tree;
    }
}
