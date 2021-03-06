<?php

namespace Propel\Generator\Builder\Om\Component\Object;

use Propel\Generator\Builder\Om\Component\BuildComponent;
use Propel\Generator\Builder\Om\Component\CrossRelationTrait;
use Propel\Generator\Builder\Om\Component\NamingTrait;
use Propel\Generator\Model\CrossRelation;

/**
 * Adds all add* methods for crossRelations.
 *
 * @author Marc J. Schmidt <marc@marcjschmidt.de>
 */
class CrossRelationAdderMethods extends BuildComponent
{
    use CrossRelationTrait;

    public function process()
    {
        // many-to-many relationships
        foreach ($this->getEntity()->getCrossRelations() as $crossRelation) {
            $this->addCrossAdd($crossRelation);
        }
    }

    protected function addCrossAdd(CrossRelation $crossRelation)
    {
        $relation = $crossRelation->getOutgoingRelation();
        $collName = $this->getRelationVarName($relation, true);

        $relatedObjectClassName = $this->getRelationPhpName($relation, false);
        $crossObjectClassName = $this->getClassNameFromEntity($relation->getForeignEntity());

        list ($signature, , $normalizedShortSignature) = $this->getCrossRelationAddMethodInformation($crossRelation, $relation);

        $body = <<<EOF
if (!\$this->{$collName}->contains({$normalizedShortSignature})) {
    \$this->{$collName}->push({$normalizedShortSignature});
    
    //update cross relation collection        
    \$crossEntity = null;
    
    if (!{$normalizedShortSignature}->isNew()) {
        \$crossEntity = \Propel\Runtime\Configuration::getCurrentConfiguration()
            ->getRepository('{$crossRelation->getMiddleEntity()->getName()}')
            ->createQuery()
            ->filterBy{$this->getRelationPhpName($crossRelation->getIncomingRelation())}(\$this)
            ->filterBy{$relatedObjectClassName}({$normalizedShortSignature})
            ->findOne();
    }
    
    if (null === \$crossEntity) {    
        \$crossEntity = new {$crossRelation->getMiddleEntity()->getName()}();
        \$crossEntity->set{$relatedObjectClassName}({$normalizedShortSignature});
        \$crossEntity->set{$this->getRelationPhpName($crossRelation->getIncomingRelation())}(\$this);
    }
    
    \$this->add{$this->getRefRelationPhpName($crossRelation->getIncomingRelation())}(\$crossEntity);

    //setup bidirectional relation
    {$this->getBiDirectional($crossRelation)}
}

return \$this;
EOF;


        $description = <<<EOF
Associate a $crossObjectClassName to this object
through the {$crossRelation->getMiddleEntity()->getFullClassName()} cross reference entity.
EOF;

        $method = $this->addMethod('add' . $relatedObjectClassName)
            ->setDescription($description)
            ->setType($this->getObjectClassName())
            ->setTypeDescription("The current object (for fluent API support)")
            ->setBody($body)
        ;

        foreach ($signature as $parameter) {
            $method->addParameter($parameter);
        }
    }

    protected function getBiDirectional(CrossRelation $crossRelation)
    {
        $getterName = 'get' . $this->getRelationPhpName($crossRelation->getIncomingRelation(), true);
        $relation = $crossRelation->getOutgoingRelation();
        $varName = $this->getRelationVarName($relation);

        $body = "
    // set the back reference to this object directly as using provided method either results
    // in endless loop or in multiple relations
    if (!\${$varName}->{$getterName}()->contains(\$this)) {
        \${$varName}->{$getterName}()->push(\$this);
    }";

        return $body;
    }
}